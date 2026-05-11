<?php

declare(strict_types=1);

namespace Tests\Unit\Trf;

use Jef\Trf\TrfParser;
use PHPUnit\Framework\TestCase;

final class TrfParserTest extends TestCase
{
    private TrfParser $parser;

    protected function setUp(): void
    {
        $this->parser = new TrfParser();
    }

    public function testParsesValidTrfFile(): void
    {
        $content = file_get_contents(__DIR__ . '/../../fixtures/sample.trf');
        $result = $this->parser->parse($content);

        $this->assertArrayHasKey('tournament', $result);
        $this->assertArrayHasKey('players', $result);
        $this->assertSame('JEF 2026 Etape 1 Rounds 1 - 9', $result['tournament']->name);
        $this->assertSame('Anderlues', $result['tournament']->city);
        $this->assertSame('2026-01-31', $result['tournament']->dateStart);
        $this->assertCount(123, $result['players']);
    }

    public function testExtractsPlayerName(): void
    {
        $content = file_get_contents(__DIR__ . '/../../fixtures/sample.trf');
        $result = $this->parser->parse($content);
        $player = $result['players'][0];

        $this->assertSame('Drugmand', $player->lastName);
        $this->assertSame('Benoit', $player->firstName);
    }

    public function testExtractsPlayerBirthDate(): void
    {
        $content = file_get_contents(__DIR__ . '/../../fixtures/sample.trf');
        $result = $this->parser->parse($content);
        $player = $result['players'][0];

        $this->assertSame('2012-08-12', $player->birthDate);
    }

    public function testExtractsFideId(): void
    {
        $content = file_get_contents(__DIR__ . '/../../fixtures/sample.trf');
        $result = $this->parser->parse($content);

        // Player 1 (Drugmand) has FIDE ID 271543
        $player1 = $result['players'][0];
        $this->assertSame(271543, $player1->fideId);

        // Player 7 (Sierakowski) has 9-digit ID 548004391
        $player7 = $result['players'][6];
        $this->assertSame(548004391, $player7->fideId);
    }

    public function testHandlesZeroFideId(): void
    {
        $content = file_get_contents(__DIR__ . '/../../fixtures/sample.trf');
        $result = $this->parser->parse($content);

        // Find a player with fide_id = 0 (stored as null)
        $playerWithZeroId = null;
        foreach ($result['players'] as $p) {
            if ($p->lastName === 'Gregoire' && $p->firstName === 'Samuel') {
                $playerWithZeroId = $p;
                break;
            }
        }
        $this->assertNotNull($playerWithZeroId);
        $this->assertNull($playerWithZeroId->fideId);
    }

    public function testExtractsPointsAndRank(): void
    {
        $content = file_get_contents(__DIR__ . '/../../fixtures/sample.trf');
        $result = $this->parser->parse($content);
        $player = $result['players'][0]; // Drugmand

        $this->assertSame(8.5, $player->points);
        $this->assertSame(3, $player->rank);
    }

    public function testExtractsRoundResults(): void
    {
        $content = file_get_contents(__DIR__ . '/../../fixtures/sample.trf');
        $result = $this->parser->parse($content);
        $player = $result['players'][0]; // Drugmand

        $this->assertCount(9, $player->rounds);

        // Round 1: vs player 61, white, win
        $this->assertSame(1, $player->rounds[0]['round']);
        $this->assertSame(61, $player->rounds[0]['opponent_rank']);
        $this->assertSame('w', $player->rounds[0]['color']);
        $this->assertSame('1', $player->rounds[0]['result']);

        // Round 5: vs player 2, white, draw
        $this->assertSame(5, $player->rounds[4]['round']);
        $this->assertSame(2, $player->rounds[4]['opponent_rank']);
        $this->assertSame('w', $player->rounds[4]['color']);
        $this->assertSame('=', $player->rounds[4]['result']);
    }

    public function testHandlesByeResults(): void
    {
        $content = file_get_contents(__DIR__ . '/../../fixtures/sample.trf');
        $result = $this->parser->parse($content);

        // Player 119 (Salem) has "0000 - U" and "0000 - Z" rounds
        $salem = null;
        foreach ($result['players'] as $p) {
            if ($p->lastName === 'Salem') {
                $salem = $p;
                break;
            }
        }
        $this->assertNotNull($salem);

        // Should have rounds with null opponent for byes
        $byeRounds = array_filter($salem->rounds, fn($r) => $r['opponent_rank'] === null);
        $this->assertNotEmpty($byeRounds);
    }

    public function testExtractsNameWithParticle(): void
    {
        $content = file_get_contents(__DIR__ . '/../../fixtures/sample.trf');
        $result = $this->parser->parse($content);

        // Player 23: "De Wilde, Victor" — the "De" prefix must not be stripped
        $deWilde = null;
        foreach ($result['players'] as $p) {
            if ($p->firstName === 'Victor' && $p->startingRank === 23) {
                $deWilde = $p;
                break;
            }
        }
        $this->assertNotNull($deWilde);
        $this->assertSame('De Wilde', $deWilde->lastName);
        $this->assertSame('Victor', $deWilde->firstName);
    }

    public function testRejectsInvalidFile(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $content = file_get_contents(__DIR__ . '/../../fixtures/invalid.trf');
        $this->parser->parse($content);
    }

    public function testRejectsMissingTournamentName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing tournament name');

        $this->parser->parse("022 SomeCity\n032 BEL\n");
    }

    public function testExtractsTournamentMetadata(): void
    {
        $content = file_get_contents(__DIR__ . '/../../fixtures/sample.trf');
        $result = $this->parser->parse($content);
        $tournament = $result['tournament'];

        $this->assertSame('BEL', $tournament->federation);
        $this->assertSame(123, $tournament->playerCount);
        $this->assertSame(9, $tournament->roundCount);
    }

    public function testExtractsRating(): void
    {
        $content = file_get_contents(__DIR__ . '/../../fixtures/sample.trf');
        $result = $this->parser->parse($content);

        // Player 1 has rating 1920
        $this->assertSame(1920, $result['players'][0]->fideRating);

        // Find a player with rating 0 (stored as null)
        $unrated = null;
        foreach ($result['players'] as $p) {
            if ($p->fideRating === null) {
                $unrated = $p;
                break;
            }
        }
        $this->assertNotNull($unrated, 'Should find at least one unrated player');
    }

    public function testNormalizesUtf8NfcNamesUnchanged(): void
    {
        $nfc = "Lef\u{00E8}bvre,Mah\u{00E9}";
        $result = $this->parser->parse($this->buildMinimalTrf($nfc));

        $player = $result['players'][0];
        $this->assertSame("Lef\u{00E8}bvre", $player->lastName);
        $this->assertSame("Mah\u{00E9}", $player->firstName);
    }

    public function testNormalizesUtf8NfdNamesToNfc(): void
    {
        // Decomposed: e + combining grave (U+0300), e + combining acute (U+0301)
        $nfd = "Lefe\u{0300}bvre,Mahe\u{0301}";
        $this->assertFalse(\Normalizer::isNormalized($nfd, \Normalizer::FORM_C));

        $result = $this->parser->parse($this->buildMinimalTrf($nfd));

        $player = $result['players'][0];
        $this->assertSame("Lef\u{00E8}bvre", $player->lastName);
        $this->assertSame("Mah\u{00E9}", $player->firstName);
        $this->assertTrue(\Normalizer::isNormalized($player->lastName, \Normalizer::FORM_C));
    }

    public function testNormalizesWindows1252EncodedNames(): void
    {
        // "Lefèbvre,Mahé" with è=0xE8 and é=0xE9 (single-byte Windows-1252)
        $latin1 = "Lef\xE8bvre,Mah\xE9";
        $this->assertFalse(mb_check_encoding($latin1, 'UTF-8'));

        $result = $this->parser->parse($this->buildMinimalTrf($latin1));

        $player = $result['players'][0];
        $this->assertSame("Lef\u{00E8}bvre", $player->lastName);
        $this->assertSame("Mah\u{00E9}", $player->firstName);
        $this->assertTrue(mb_check_encoding($player->lastName, 'UTF-8'));
        $this->assertTrue(mb_check_encoding($player->firstName, 'UTF-8'));
    }

    public function testAllEncodingsProduceIdenticalBytes(): void
    {
        // Same human, three encodings — bytes stored must be identical
        // so (last_name, first_name, birth_date) lookups deduplicate.
        $nfc = "Lef\u{00E8}bvre,Mah\u{00E9}";
        $nfd = "Lefe\u{0300}bvre,Mahe\u{0301}";
        $latin1 = "Lef\xE8bvre,Mah\xE9";

        $a = $this->parser->parse($this->buildMinimalTrf($nfc))['players'][0];
        $b = $this->parser->parse($this->buildMinimalTrf($nfd))['players'][0];
        $c = $this->parser->parse($this->buildMinimalTrf($latin1))['players'][0];

        $this->assertSame(bin2hex($a->lastName), bin2hex($b->lastName));
        $this->assertSame(bin2hex($a->lastName), bin2hex($c->lastName));
        $this->assertSame(bin2hex($a->firstName), bin2hex($b->firstName));
        $this->assertSame(bin2hex($a->firstName), bin2hex($c->firstName));
    }

    public function testNormalizesHeaderFields(): void
    {
        // Tournament name and city in Windows-1252
        $trf = "012 JEF \xC9tape\n"        // "JEF Étape"
             . "022 Li\xE8ge\n"             // "Liège"
             . "042 2026/01/31\n"
             . $this->buildPlayerLine("Doe,John") . "\n";

        $result = $this->parser->parse($trf);

        $this->assertSame("JEF \u{00C9}tape", $result['tournament']->name);
        $this->assertSame("Li\u{00E8}ge", $result['tournament']->city);
    }

    /**
     * Build a minimal valid TRF with a single player record whose
     * name field (cols 14-47, 34 bytes) contains the raw bytes of $name.
     */
    private function buildMinimalTrf(string $name): string
    {
        return "012 Test Tournament\n"
             . "042 2026/01/31\n"
             . $this->buildPlayerLine($name) . "\n";
    }

    /**
     * Pack a TRF player record. $name is written byte-for-byte into the
     * 34-byte name field. Padding uses spaces so byte offsets line up.
     */
    private function buildPlayerLine(string $name): string
    {
        $name = substr($name, 0, 34);
        $name = str_pad($name, 34, ' ', STR_PAD_RIGHT);
        return '001'                       // cols 0-2
             . ' '                         // col 3
             . '   1'                      // cols 4-7: starting rank
             . ' '                         // col 8
             . 'm'                         // col 9: sex
             . ' '                         // col 10
             . '   '                       // cols 11-13: title (empty)
             . $name                       // cols 14-47: 34-byte name
             . '   0'                      // cols 48-51: rating
             . ' '                         // col 52
             . 'BEL'                       // cols 53-55: federation
             . ' '                         // col 56
             . '           '               // cols 57-67: FIDE ID (11 chars)
             . ' '                         // col 68
             . '2013/03/17'                // cols 69-78: birth date
             . ' '                         // col 79
             . ' 0.0'                      // cols 80-83: points
             . ' '                         // col 84
             . '   1'                      // cols 85-88: final rank
             . ' ';                        // col 89
    }
}
