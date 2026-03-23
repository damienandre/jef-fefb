<?php

declare(strict_types=1);

namespace Jef\Trf;

final class TrfParser
{
    /**
     * TRF16 player record (001) field positions (0-indexed from after "001"):
     * Pos 0-3:   Starting rank (4 chars)
     * Pos 4:     Space
     * Pos 5:     Sex (1 char)
     * Pos 6:     Space
     * Pos 7-9:   Title (3 chars, but typically 2 + space)
     * Pos 10:    Space (sometimes part of title)
     * Pos 10-43: Name (33 chars) — but in practice the title is 2 chars + space, then name starts at pos 10
     *
     * Given the variability, we use a regex that is more tolerant of spacing.
     */
    private const PLAYER_REGEX = '/^001\s*(?P<rank>\d+)\s+(?P<sex>[mwf ])\s+(?P<title>\w{0,3})\s+(?P<name>[^,]+,\s*\S[^0-9]*?)\s+(?P<rating>\d+)\s+(?P<fed>[A-Z]{3})\s+(?P<id>\d+)\s+(?P<birth>[\d\/.\-]+)\s+(?P<points>[\d.]+)\s+(?P<finalrank>\d+)\s+(?P<rounds>.*)$/';

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
        if (!preg_match(self::PLAYER_REGEX, $line, $m)) {
            throw new \InvalidArgumentException(
                'Invalid player record: ' . substr($line, 0, 80)
            );
        }

        $nameRaw = trim($m['name']);
        $nameParts = explode(',', $nameRaw, 2);
        $lastName = trim($nameParts[0] ?? '');
        $firstName = trim($nameParts[1] ?? '');

        $fideId = (int) $m['id'];
        $rating = (int) $m['rating'];

        $rounds = $this->parseRounds($m['rounds']);

        $birthDate = $this->parseBirthDate(trim($m['birth']));

        return new TrfPlayer(
            startingRank: (int) $m['rank'],
            sex: trim($m['sex']) ?: null,
            title: trim($m['title']) ?: null,
            lastName: $lastName,
            firstName: $firstName,
            fideRating: $rating > 0 ? $rating : null,
            federation: trim($m['fed']) ?: null,
            fideId: $fideId > 0 ? $fideId : null,
            birthDate: $birthDate,
            points: (float) $m['points'],
            rank: (int) $m['finalrank'] ?: null,
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
