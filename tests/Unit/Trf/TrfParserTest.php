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
}
