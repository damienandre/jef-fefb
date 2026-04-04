<?php

declare(strict_types=1);

namespace Jef\Trf;

final class TrfParser
{
    /**
     * TRF16 player record (001) fixed-width field positions (0-indexed):
     * Col 0-2:   "001" record identifier
     * Col 3:     Space
     * Col 4-7:   Starting rank (4 chars, right-justified)
     * Col 8:     Space
     * Col 9:     Sex (1 char)
     * Col 10:    Space
     * Col 11-13: Title (3 chars)
     * Col 14-47: Name (34 chars)
     * Col 48-51: Rating (4 chars)
     * Col 52:    Space
     * Col 53-55: Federation (3 chars)
     * Col 56:    Space
     * Col 57-67: FIDE ID (11 chars)
     * Col 68:    Space
     * Col 69-78: Birth date (10 chars)
     * Col 79:    Space
     * Col 80-83: Points (4 chars)
     * Col 84:    Space
     * Col 85-88: Final rank (4 chars)
     * Col 89:    Space
     * Col 90+:   Round results (10 chars each)
     */

    /**
     * @return array{tournament: TrfTournament, players: TrfPlayer[]}
     * @throws \InvalidArgumentException on invalid TRF content
     */
    public function parse(string $content): array
    {
        $lines = preg_split('/\r?\n/', $content);
        $headers = [];
        $playerLines = [];

        foreach ($lines as $line) {
            if ($line === '' || strlen($line) < 3) {
                continue;
            }

            $din = substr($line, 0, 3);
            $data = strlen($line) > 4 ? substr($line, 4) : '';

            if ($din === '001') {
                $playerLines[] = $line;
            } elseif (preg_match('/^\d{3}$/', $din)) {
                $headers[$din] = trim($data);
            }
        }

        if (empty($headers['012'])) {
            throw new \InvalidArgumentException('Missing tournament name (012 header)');
        }

        $roundDates = [];
        if (!empty($headers['132'])) {
            $roundDates = preg_split('/\s+/', trim($headers['132']));
        }

        $roundCount = count($roundDates);

        $dateStart = $this->parseDate($headers['042'] ?? null);
        if ($dateStart === null) {
            throw new \InvalidArgumentException('Missing or invalid tournament start date (042 header)');
        }

        $tournament = new TrfTournament(
            name: $headers['012'],
            city: $headers['022'] ?? null,
            federation: !empty($headers['032']) ? $headers['032'] : null,
            dateStart: $dateStart,
            dateEnd: $this->parseDate($headers['052'] ?? null),
            playerCount: (int) ($headers['062'] ?? count($playerLines)),
            roundCount: $roundCount,
            arbiter: $headers['102'] ?? null,
            timeControl: $headers['122'] ?? null,
            roundDates: $roundDates,
        );

        $players = [];
        foreach ($playerLines as $line) {
            $player = $this->parsePlayerLine($line);
            if ($player !== null) {
                $players[] = $player;
                // Infer round count from first player if header didn't specify
                if ($roundCount === 0 && $tournament->roundCount === 0) {
                    $roundCount = count($player->rounds);
                }
            }
        }

        if (!empty($players) && $tournament->roundCount === 0 && $roundCount > 0) {
            $tournament = new TrfTournament(
                name: $tournament->name,
                city: $tournament->city,
                federation: $tournament->federation,
                dateStart: $tournament->dateStart,
                dateEnd: $tournament->dateEnd,
                playerCount: $tournament->playerCount,
                roundCount: $roundCount,
                arbiter: $tournament->arbiter,
                timeControl: $tournament->timeControl,
                roundDates: $tournament->roundDates,
            );
        }

        if (empty($players)) {
            throw new \InvalidArgumentException('No valid player records found');
        }

        return ['tournament' => $tournament, 'players' => $players];
    }

    private function parsePlayerLine(string $line): ?TrfPlayer
    {
        if (strlen($line) < 90) {
            throw new \InvalidArgumentException(
                'Invalid player record (too short): ' . substr($line, 0, 80)
            );
        }

        $rank = (int) trim(substr($line, 4, 4));
        $sex = trim(substr($line, 9, 1)) ?: null;
        $title = trim(substr($line, 11, 3)) ?: null;

        $nameRaw = trim(substr($line, 14, 34));
        $nameParts = explode(',', $nameRaw, 2);
        $lastName = trim($nameParts[0] ?? '');
        $firstName = trim($nameParts[1] ?? '');

        $rating = (int) trim(substr($line, 48, 4));
        $federation = trim(substr($line, 53, 3)) ?: null;
        $fideId = (int) trim(substr($line, 57, 11));
        $birthDate = $this->parseBirthDate(trim(substr($line, 69, 10)));
        $points = (float) trim(substr($line, 80, 4));
        $finalRank = (int) trim(substr($line, 85, 4));

        $roundsStr = strlen($line) > 90 ? substr($line, 90) : '';
        $rounds = $this->parseRounds($roundsStr);

        return new TrfPlayer(
            startingRank: $rank,
            sex: $sex,
            title: $title,
            lastName: $lastName,
            firstName: $firstName,
            fideRating: $rating > 0 ? $rating : null,
            federation: $federation,
            fideId: $fideId > 0 ? $fideId : null,
            birthDate: $birthDate,
            points: $points,
            rank: $finalRank ?: null,
            rounds: $rounds,
        );
    }

    private function parseRounds(string $roundsStr): array
    {
        $rounds = [];
        $roundsStr = rtrim($roundsStr);

        // Match round blocks: opponent (4 chars or "0000"), space, color, space, result
        preg_match_all('/\s*(\d{1,4}|0000)\s+([bwsf\-])\s+([1=0+\-hfuzUwdlWDLZ])/i', $roundsStr, $matches, PREG_SET_ORDER);

        $roundNum = 1;
        foreach ($matches as $match) {
            $opponent = (int) $match[1];
            $color = strtolower($match[2]);
            $result = $match[3];

            // Normalize result codes
            $result = match (strtoupper($result)) {
                'W' => '1',
                'D' => '=',
                'L' => '0',
                'U' => '-',
                'Z' => '-',
                default => $result,
            };

            $rounds[] = [
                'round' => $roundNum,
                'opponent_rank' => $opponent > 0 ? $opponent : null,
                'color' => $color !== '-' ? $color : null,
                'result' => $result,
            ];

            $roundNum++;
        }

        return $rounds;
    }

    private function parseBirthDate(string $dateStr): ?string
    {
        if ($dateStr === '' || $dateStr === '0000/00/00' || $dateStr === '0000.00.00') {
            return null;
        }
        // YYYY/MM/DD or YYYY.MM.DD or YYYY-MM-DD
        if (preg_match('/^(\d{4})[\/.\-](\d{2})[\/.\-](\d{2})$/', $dateStr, $m)) {
            return "{$m[1]}-{$m[2]}-{$m[3]}";
        }
        // DD.MM.YYYY or DD/MM/YYYY
        if (preg_match('/^(\d{2})[\/.](\d{2})[\/.](\d{4})$/', $dateStr, $m)) {
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }
        return null;
    }

    private function parseDate(?string $dateStr): ?string
    {
        if ($dateStr === null || trim($dateStr) === '') {
            return null;
        }
        return $this->parseBirthDate(trim($dateStr));
    }
}
