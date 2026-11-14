<?php

declare(strict_types=1);

namespace Jef\Frbe;

use PDO;

/**
 * {@see FrbeApi} answered from the federation's bulk player export
 * (`players.sqlite`, see {@see FrbeExportDownloader}).
 *
 * The FRBE/KBSB member REST endpoints were decommissioned in the federation's
 * August 2026 move to Odoo, and the bulk export is the only source the
 * federation commits to. The export carries every player (~36k), the club
 * reference list, and FIDE ids, so a whole enrichment run costs one download
 * instead of several HTTP calls per player.
 *
 * The file is opened lazily: building the object costs nothing, and a run that
 * enriches nobody never touches the network. If the export cannot be obtained,
 * every lookup resolves to null / [] (enrichment is best-effort), and
 * {@see error()} explains why.
 *
 * Export traps handled here:
 *   - `G = 1` marks a provisional licence. Its `IdNumber` is negative and is
 *     replaced by a new matricule once the player joins a club, so it is never
 *     returned as a Belgian id (jef_players.belgian_id is also unsigned). Its
 *     club is still used.
 *   - The national rating (`Elo`) was discontinued and is empty on every row,
 *     so member records carry no rating.
 */
final class FrbeExport implements FrbeApi
{
    private ?PDO $pdo = null;
    private bool $opened = false;
    private ?string $error = null;

    /**
     * @param \Closure(): string $sqlitePath returns the path of a local
     *        players.sqlite; may throw on failure. Called at most once.
     */
    public function __construct(private \Closure $sqlitePath) {}

    /** Production source: downloads (or reuses a cached copy of) the export. */
    public static function live(?FrbeExportDownloader $downloader = null, bool $forceRefresh = false): self
    {
        $downloader ??= new FrbeExportDownloader();

        return new self(static fn(): string => $downloader->fetch($forceRefresh));
    }

    /** Source backed by an existing players.sqlite (tests, manual runs). */
    public static function fromFile(string $path): self
    {
        return new self(static fn(): string => $path);
    }

    /** Why the export could not be opened, or null if it opened (or was never needed). */
    public function error(): ?string
    {
        return $this->error;
    }

    public function listClubs(): array
    {
        $pdo = $this->db();
        if ($pdo === null) {
            return [];
        }

        $clubs = [];
        foreach ($pdo->query('SELECT Club, Name FROM clubs WHERE Club > 0 ORDER BY Club') as $row) {
            $name = trim((string) ($row['Name'] ?? ''));
            $clubs[] = [
                'idclub' => (int) $row['Club'],
                'name_long' => $name !== '' ? $name : (string) $row['Club'],
                // The export has no short name and no enabled flag; every club
                // it lists is current.
                'name_short' => null,
                'enabled' => true,
            ];
        }

        return $clubs;
    }

    public function fideIdToBelgianId(int $fideId): ?int
    {
        $row = $this->rowByFideId($fideId);
        if ($row === null || (int) $row['G'] === 1) {
            return null;
        }
        $belId = (int) $row['IdNumber'];

        return $belId > 0 ? $belId : null;
    }

    public function getMember(int $belgianId): ?array
    {
        if ($belgianId <= 0) {
            return null;
        }
        $pdo = $this->db();
        if ($pdo === null) {
            return null;
        }
        $stmt = $pdo->prepare('SELECT * FROM players WHERE IdNumber = ?');
        $stmt->execute([$belgianId]);
        $row = $stmt->fetch();

        return is_array($row) ? self::toMember($row) : null;
    }

    public function getFideMember(int $fideId): ?array
    {
        $row = $this->rowByFideId($fideId);

        return $row !== null ? self::toMember($row) : null;
    }

    /**
     * Row for a FIDE id, preferring a definitive record over a provisional
     * (G) one, then an affiliated one, should the export ever hold several.
     *
     * @return array<string, mixed>|null
     */
    private function rowByFideId(int $fideId): ?array
    {
        if ($fideId <= 0) {
            return null;
        }
        $pdo = $this->db();
        if ($pdo === null) {
            return null;
        }
        $stmt = $pdo->prepare(
            'SELECT * FROM players WHERE FideId = ?
             ORDER BY COALESCE(G, 0) ASC, COALESCE(Affiliated, 0) DESC, IdNumber DESC
             LIMIT 1'
        );
        $stmt->execute([$fideId]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string, mixed> $row
     * @return array{idnumber:int, idfide:int, idclub:int, first_name:string, last_name:string, birthyear:int, affiliated:bool, provisional:bool}
     */
    private static function toMember(array $row): array
    {
        // Name is "Last, First".
        $parts = array_map('trim', explode(',', (string) ($row['Name'] ?? ''), 2));

        return [
            'idnumber' => (int) $row['IdNumber'],
            'idfide' => (int) ($row['FideId'] ?? 0),
            'idclub' => (int) ($row['Club'] ?? 0),
            'first_name' => $parts[1] ?? '',
            'last_name' => $parts[0],
            // Birthday is year-only since the migration; tolerate full dates.
            'birthyear' => (int) substr((string) ($row['Birthday'] ?? ''), 0, 4),
            'affiliated' => (int) ($row['Affiliated'] ?? 0) === 1,
            'provisional' => (int) ($row['G'] ?? 0) === 1,
        ];
    }

    private function db(): ?PDO
    {
        if ($this->opened) {
            return $this->pdo;
        }
        $this->opened = true;

        try {
            $path = ($this->sqlitePath)();
            $pdo = new PDO('sqlite:' . $path, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            // The export only indexes IdNumber/Club/Name. FideId is the main
            // lookup key here, so index it in our local copy (~36k rows, fast).
            // Best-effort: a read-only file still works, just with scans.
            try {
                $pdo->exec('CREATE INDEX IF NOT EXISTS jef_idx_fideid ON players (FideId)');
            } catch (\PDOException) {
            }
            // Fail fast on a file that is not the expected export.
            $pdo->query('SELECT IdNumber, FideId, Club, G, Name, Birthday, Affiliated FROM players LIMIT 1');
            $pdo->query('SELECT Club, Name FROM clubs LIMIT 1');
            $this->pdo = $pdo;
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();
        }

        return $this->pdo;
    }
}
