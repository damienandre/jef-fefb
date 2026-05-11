<?php

declare(strict_types=1);

namespace Tests\Integration;

use Jef\Database;
use Jef\PlayerNormalizer;
use PHPUnit\Framework\TestCase;

final class PlayerNormalizerTest extends TestCase
{
    private static ?\PDO $db = null;

    public static function setUpBeforeClass(): void
    {
        $configFile = __DIR__ . '/../../config.php';
        if (!file_exists($configFile)) {
            self::markTestSkipped('config.php not found — integration tests skipped');
        }
        try {
            self::$db = Database::get();
        } catch (\PDOException $e) {
            self::markTestSkipped('Database not available: ' . $e->getMessage());
        }
    }

    protected function setUp(): void
    {
        if (self::$db === null) {
            $this->markTestSkipped('No database connection');
        }
        self::$db->exec("DELETE FROM jef_circuit_results");
        self::$db->exec("DELETE FROM jef_circuit_rankings");
        self::$db->exec("DELETE FROM jef_tournament_players");
        self::$db->exec("DELETE FROM jef_tournaments");
        self::$db->exec("DELETE FROM jef_players");
        self::$db->exec("DELETE FROM jef_seasons");
    }

    public function testMergesOrphanWithFideIntoActivePlayer(): void
    {
        // Reproduces the production state that motivated this backfill:
        // - row A: has FIDE id, no tournament participation (the accidental row
        //   created when the admin attributed the FIDE id to one of the duplicates)
        // - row B: no FIDE id, has all the tournament data (the "real" row that
        //   actually shows up in the public ranking)
        // After backfill: A absorbs B, FIDE id stays, B's TP rows now point at A.
        [$seasonId, $tournamentId] = $this->seedSeasonAndTournament();

        self::$db->exec(
            "INSERT INTO jef_players (fide_id, last_name, first_name, birth_date)
             VALUES (548011010, 'Lef\u{00E8}bvre', 'Mah\u{00E9}', '2013-03-17')"
        );
        $orphanId = (int) self::$db->lastInsertId();

        self::$db->exec(
            "INSERT INTO jef_players (fide_id, last_name, first_name, birth_date)
             VALUES (NULL, 'Lef\u{00E8}bvre', 'Mah\u{00E9}', '2013-03-17')"
        );
        $activeId = (int) self::$db->lastInsertId();

        self::$db->prepare(
            "INSERT INTO jef_tournament_players
             (tournament_id, player_id, starting_rank, final_rank, points, rounds_data)
             VALUES (?, ?, 1, 1, 5.0, '[]')"
        )->execute([$tournamentId, $activeId]);

        $report = PlayerNormalizer::run(self::$db, false);

        $this->assertSame(1, (int) self::$db->query("SELECT COUNT(*) FROM jef_players")->fetchColumn());

        $remaining = self::$db->query("SELECT id, fide_id FROM jef_players")->fetch();
        $this->assertSame($orphanId, (int) $remaining['id'], 'Row with FIDE id wins as canonical');
        $this->assertSame(548011010, (int) $remaining['fide_id']);

        $tp = self::$db->query("SELECT player_id FROM jef_tournament_players")->fetch();
        $this->assertSame($orphanId, (int) $tp['player_id']);

        $this->assertCount(1, $report['merged']);
        $this->assertSame($orphanId, $report['merged'][0]['canonical_id']);
        $this->assertSame([$activeId], $report['merged'][0]['duplicate_ids']);
        $this->assertSame(1, $report['merged'][0]['tournaments_moved']);
        $this->assertSame([$seasonId], $report['seasons_recalculated']);

        $crankCount = (int) self::$db->query(
            "SELECT COUNT(*) FROM jef_circuit_rankings WHERE ranking_type = 'general'"
        )->fetchColumn();
        $this->assertSame(1, $crankCount, 'Ranking should be rebuilt for the merged player');
    }

    public function testSkipsClusterWithDistinctFideIds(): void
    {
        self::$db->exec(
            "INSERT INTO jef_players (fide_id, last_name, first_name, birth_date)
             VALUES (111, 'Doe', 'John', '2010-01-01')"
        );
        $idA = (int) self::$db->lastInsertId();

        self::$db->exec(
            "INSERT INTO jef_players (fide_id, last_name, first_name, birth_date)
             VALUES (222, 'Doe', 'John', '2010-01-01')"
        );
        $idB = (int) self::$db->lastInsertId();

        $report = PlayerNormalizer::run(self::$db, false);

        $this->assertSame(2, (int) self::$db->query("SELECT COUNT(*) FROM jef_players")->fetchColumn());
        $this->assertCount(0, $report['merged']);
        $this->assertCount(1, $report['skipped']);
        $this->assertSame([$idA, $idB], $report['skipped'][0]['ids']);
        $this->assertStringContainsString('distinct FIDE ids', $report['skipped'][0]['reason']);
    }

    public function testNormalizesNfdNamesToNfc(): void
    {
        // Lefèbvre in NFD: 'e' + combining grave (U+0300)
        $nfd = "Lefe\u{0300}bvre";
        $this->assertFalse(\Normalizer::isNormalized($nfd, \Normalizer::FORM_C));

        self::$db->prepare(
            "INSERT INTO jef_players (last_name, first_name, birth_date) VALUES (?, ?, ?)"
        )->execute([$nfd, 'Test', '2010-01-01']);

        $report = PlayerNormalizer::run(self::$db, false);

        $last = self::$db->query("SELECT last_name FROM jef_players")->fetchColumn();
        $this->assertSame("Lef\u{00E8}bvre", $last);
        $this->assertCount(1, $report['renamed']);
    }

    public function testMergesOnlyAfterNormalization(): void
    {
        // Same human, two encodings — they only collide after the normalization
        // pass rewrites both to the same bytes. Confirms step order: normalize
        // first, THEN merge.
        $nfc = "Lef\u{00E8}bvre";
        $nfd = "Lefe\u{0300}bvre";

        self::$db->prepare(
            "INSERT INTO jef_players (last_name, first_name, birth_date) VALUES (?, ?, ?)"
        )->execute([$nfc, 'Test', '2010-01-01']);
        self::$db->prepare(
            "INSERT INTO jef_players (last_name, first_name, birth_date) VALUES (?, ?, ?)"
        )->execute([$nfd, 'Test', '2010-01-01']);

        PlayerNormalizer::run(self::$db, false);

        $this->assertSame(1, (int) self::$db->query("SELECT COUNT(*) FROM jef_players")->fetchColumn());
    }

    public function testDryRunDoesNotPersistChanges(): void
    {
        [$seasonId, $tournamentId] = $this->seedSeasonAndTournament();

        self::$db->exec(
            "INSERT INTO jef_players (fide_id, last_name, first_name, birth_date)
             VALUES (548011010, 'Lef\u{00E8}bvre', 'Mah\u{00E9}', '2013-03-17'),
                    (NULL,      'Lef\u{00E8}bvre', 'Mah\u{00E9}', '2013-03-17')"
        );

        $report = PlayerNormalizer::run(self::$db, true);

        $this->assertSame(2, (int) self::$db->query("SELECT COUNT(*) FROM jef_players")->fetchColumn(),
            'Dry run must not modify data');
        $this->assertCount(1, $report['merged'], 'Dry run still produces the merge plan');
    }

    /**
     * @return array{0:int, 1:int}
     */
    private function seedSeasonAndTournament(): array
    {
        self::$db->exec("INSERT INTO jef_seasons (year) VALUES (2026)");
        $seasonId = (int) self::$db->lastInsertId();
        self::$db->prepare(
            "INSERT INTO jef_tournaments
             (season_id, name, date_start, round_count, player_count, sort_order)
             VALUES (?, 'T1', '2026-01-31', 1, 1, 1)"
        )->execute([$seasonId]);
        return [$seasonId, (int) self::$db->lastInsertId()];
    }
}
