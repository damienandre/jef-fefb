<?php

declare(strict_types=1);

namespace Jef\Frbe;

/**
 * Locates, downloads and unpacks the FRBE/KBSB bulk player export:
 * https://www.frbe-kbsb.be/sites/manager/ELO/players_YYYYMM.zip, a zip holding
 * one `players.sqlite` (~1.9 MB zipped, ~4 MB unpacked, refreshed several times
 * a day).
 *
 * Traps (verified 2026-11-14):
 *   - The file name is not the current month: the live file is updated in
 *     place (`players_202608.zip` modified in November). So the newest export
 *     is picked from the Apache directory index by modification time.
 *   - A file that does not exist answers 301 to the federation blog, not 404.
 *     Redirects are never followed and only HTTP 200 is believed.
 *   - The directory also holds one-off dumps (`players_20260814_001.zip`,
 *     `players_202307-safet.zip`) and unrelated files; only the exact
 *     `players_YYYYMM.zip` shape is a full roster.
 *   - Two hosts one letter apart: the export lives on `www.frbe-kbsb.be`, the
 *     old API on `www.frbe-kbsb-ksb.be`.
 *
 * The unpacked file is cached on disk so that several imports in a row, or an
 * import followed by cli/enrich-players.php, download it once. If a refresh
 * fails, a stale cached copy is used rather than nothing.
 */
final class FrbeExportDownloader
{
    public const BASE_URL = 'https://www.frbe-kbsb.be/sites/manager/ELO/';
    private const MEMBER_NAME = 'players.sqlite';
    private const MAX_INDEX_BYTES = 2 * 1024 * 1024;
    private const MAX_ZIP_BYTES = 32 * 1024 * 1024;
    private const MAX_SQLITE_BYTES = 128 * 1024 * 1024;
    private const MONTHS = [
        'jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4, 'may' => 5, 'jun' => 6,
        'jul' => 7, 'aug' => 8, 'sep' => 9, 'oct' => 10, 'nov' => 11, 'dec' => 12,
    ];

    private string $cacheFile;
    private ?string $refreshError = null;

    /**
     * @param string|null $cacheFile where the unpacked players.sqlite is kept;
     *        defaults to var/cache/frbe-players.sqlite in the project root.
     * @param int $maxAgeSeconds reuse the cached copy without any network call
     *        while it is younger than this.
     * @param int $timeoutSeconds per-request transfer timeout (the web import
     *        passes a short one; the CLI can afford the default).
     * @param int $retryAfterSeconds after a failed download, don't try again
     *        (unless forced) for this long, so an FRBE outage doesn't make every
     *        import wait for the timeouts.
     * @param int $maxStaleSeconds when a refresh fails, fall back to the cached
     *        copy only if it is younger than this; past that, fail rather than
     *        keep enriching from data nobody notices is outdated.
     */
    public function __construct(
        ?string $cacheFile = null,
        private int $maxAgeSeconds = 4 * 3600,
        private string $baseUrl = self::BASE_URL,
        private int $timeoutSeconds = 30,
        private int $retryAfterSeconds = 30 * 60,
        private int $maxStaleSeconds = 7 * 86400,
    ) {
        $this->cacheFile = $cacheFile ?? dirname(__DIR__, 2) . '/var/cache/frbe-players.sqlite';
    }

    /**
     * Path of an up-to-date local players.sqlite.
     *
     * @param bool $force ignore the cache age and the retry delay, and download anyway
     * @throws \RuntimeException when no export can be obtained and no cached
     *         copy younger than $maxStaleSeconds exists
     */
    public function fetch(bool $force = false): string
    {
        $this->refreshError = null;
        $cached = is_file($this->cacheFile) ? filemtime($this->cacheFile) : false;
        if (!$force && $cached !== false && time() - $cached < $this->maxAgeSeconds) {
            return $this->cacheFile;
        }

        $failedAt = is_file($this->failureMarker()) ? filemtime($this->failureMarker()) : false;
        if (!$force && $failedAt !== false && time() - $failedAt < $this->retryAfterSeconds) {
            $error = 'FRBE export: last download failed at ' . date('Y-m-d H:i', $failedAt)
                . '; not retrying yet';
            return $this->fallBack($cached, $error);
        }

        try {
            $this->download();
        } catch (\RuntimeException $e) {
            @touch($this->failureMarker());
            return $this->fallBack($cached, $e->getMessage());
        }
        @unlink($this->failureMarker());

        return $this->cacheFile;
    }

    /**
     * The cached copy when a refresh is not possible, if it is not too old.
     *
     * @param int|false $cached mtime of the cached copy
     * @throws \RuntimeException when there is no usable cached copy
     */
    private function fallBack(int|false $cached, string $error): string
    {
        if ($cached === false) {
            throw new \RuntimeException($error);
        }
        $ageDays = intdiv(time() - $cached, 86400);
        if (time() - $cached >= $this->maxStaleSeconds) {
            throw new \RuntimeException("{$error} (cached copy is {$ageDays} days old, too old to use)");
        }
        $this->refreshError = $error;

        return $this->cacheFile; // stale beats nothing
    }

    /** Touched when a download fails; its mtime drives the retry delay. */
    private function failureMarker(): string
    {
        return $this->cacheFile . '.failed';
    }

    /**
     * Why the last {@see fetch()} fell back to a stale cached copy, or null if
     * it did not (fresh cache, or a successful download).
     */
    public function refreshError(): ?string
    {
        return $this->refreshError;
    }

    /**
     * Newest canonical export file name listed in the Apache directory index,
     * ordered by the index's modification column (files are updated in place,
     * so the highest YYYYMM is not necessarily the freshest), YYYYMM breaking
     * ties. Reads the href, never the link text, which Apache truncates.
     */
    public static function newestExportName(string $indexHtml): ?string
    {
        // One match per table row: the href, then the first date cell after it
        // without crossing into the next row.
        $pattern = '~href="(players_(\d{6})\.zip)"(?:(?!<tr).)*?'
            . '(\d{2})-([A-Za-z]{3})-(\d{4})\s+(\d{2}):(\d{2})~si';
        if (!preg_match_all($pattern, $indexHtml, $matches, PREG_SET_ORDER)) {
            return null;
        }

        $best = null;
        $bestKey = '';
        foreach ($matches as $m) {
            $month = self::MONTHS[strtolower($m[4])] ?? null;
            if ($month === null) {
                continue;
            }
            $key = sprintf('%s%02d%s%s%s|%s', $m[5], $month, $m[3], $m[6], $m[7], $m[2]);
            if ($key > $bestKey) {
                $bestKey = $key;
                $best = $m[1];
            }
        }

        return $best;
    }

    private function download(): void
    {
        // First, so the failure marker can be written if the network fails.
        $dir = dirname($this->cacheFile);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException("FRBE export: cannot create cache directory {$dir}");
        }

        $index = $this->get($this->baseUrl, self::MAX_INDEX_BYTES);
        $name = self::newestExportName($index);
        if ($name === null) {
            throw new \RuntimeException('FRBE export: directory index lists no players_YYYYMM.zip');
        }

        // Unique temp names: two concurrent refreshes (an admin import and the
        // CLI) must not write into, or unlink, each other's partial files.
        $tmp = $this->cacheFile . '.' . bin2hex(random_bytes(6));
        $zipFile = $tmp . '.zip.part';
        $sqliteFile = $tmp . '.part';
        try {
            file_put_contents($zipFile, $this->get($this->baseUrl . $name, self::MAX_ZIP_BYTES));
            $this->extract($zipFile, $sqliteFile);
            if (!rename($sqliteFile, $this->cacheFile)) {
                throw new \RuntimeException('FRBE export: cannot move the export into the cache');
            }
        } finally {
            @unlink($zipFile);
            @unlink($sqliteFile);
        }
    }

    private function extract(string $zipFile, string $dest): void
    {
        $zip = new \ZipArchive();
        if ($zip->open($zipFile, \ZipArchive::RDONLY) !== true) {
            throw new \RuntimeException('FRBE export: archive is not a readable zip');
        }
        try {
            // Exact name only, never extractTo(): no path from the archive is
            // used, and the declared size is checked before inflating.
            $index = $zip->locateName(self::MEMBER_NAME, \ZipArchive::FL_NOCASE | \ZipArchive::FL_NODIR);
            $stat = $index !== false ? $zip->statIndex($index) : false;
            if ($stat === false || basename($stat['name']) !== $stat['name']) {
                throw new \RuntimeException('FRBE export: archive has no top-level ' . self::MEMBER_NAME);
            }
            if ($stat['size'] > self::MAX_SQLITE_BYTES) {
                throw new \RuntimeException('FRBE export: ' . self::MEMBER_NAME . ' is too large');
            }

            $in = $zip->getStreamIndex($index);
            $out = fopen($dest, 'wb');
            if ($in === false || $out === false) {
                throw new \RuntimeException('FRBE export: cannot unpack ' . self::MEMBER_NAME);
            }
            $written = stream_copy_to_stream($in, $out, self::MAX_SQLITE_BYTES + 1);
            fclose($in);
            fclose($out);
            if ($written === false || $written > self::MAX_SQLITE_BYTES) {
                throw new \RuntimeException('FRBE export: cannot unpack ' . self::MEMBER_NAME);
            }
        } finally {
            $zip->close();
        }

        if (file_get_contents($dest, false, null, 0, 16) !== "SQLite format 3\0") {
            throw new \RuntimeException('FRBE export: ' . self::MEMBER_NAME . ' is not an SQLite database');
        }
    }

    /** GET a URL, believing only HTTP 200, aborting past $maxBytes. */
    private function get(string $url, int $maxBytes): string
    {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new \RuntimeException("FRBE export: cannot request {$url}");
        }

        $body = '';
        $tooLarge = false;
        curl_setopt_array($ch, [
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_CONNECTTIMEOUT => min(10, $this->timeoutSeconds),
            CURLOPT_WRITEFUNCTION => static function ($ch, string $chunk) use (&$body, &$tooLarge, $maxBytes): int {
                if (strlen($body) + strlen($chunk) > $maxBytes) {
                    $tooLarge = true;
                    return 0; // abort the transfer
                }
                $body .= $chunk;
                return strlen($chunk);
            },
        ]);

        $ok = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        // No curl_close(): handles are auto-freed objects since PHP 8.0 and the
        // function is deprecated in 8.5.

        if ($tooLarge) {
            throw new \RuntimeException("FRBE export: {$url} is larger than {$maxBytes} bytes");
        }
        if ($ok === false) {
            throw new \RuntimeException("FRBE export: {$url} unreachable: {$error}");
        }
        if ($status !== 200) {
            throw new \RuntimeException("FRBE export: {$url} answered HTTP {$status}");
        }

        return $body;
    }
}
