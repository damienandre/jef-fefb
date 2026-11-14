<?php

declare(strict_types=1);

namespace Tests\Unit\Frbe;

use Jef\Frbe\FrbeExportDownloader;
use PHPUnit\Framework\TestCase;

final class FrbeExportDownloaderTest extends TestCase
{
    private static function row(string $name, string $mtime): string
    {
        $text = strlen($name) > 23 ? substr($name, 0, 20) . '..&gt;' : $name;

        return '<tr><td valign="top"><img src="/icons/compressed.gif" alt="[   ]"></td>'
            . "<td><a href=\"{$name}\">{$text}</a>     </td>"
            . "<td align=\"right\">{$mtime}  </td><td align=\"right\">1.8M</td><td>&nbsp;</td></tr>\n";
    }

    public function testPicksMostRecentlyModifiedNotHighestMonth(): void
    {
        // Real situation on 2026-11-14: the August file is updated in place.
        $html = self::row('players_202604.zip', '17-Jun-2026 12:59')
            . self::row('players_202608.zip', '14-Nov-2026 19:16')
            . self::row('players_202607.zip', '11-Aug-2026 09:04');

        $this->assertSame('players_202608.zip', FrbeExportDownloader::newestExportName($html));

        $html = self::row('players_202612.zip', '01-Dec-2026 08:00')
            . self::row('players_202608.zip', '14-Nov-2026 19:16');
        $this->assertSame('players_202612.zip', FrbeExportDownloader::newestExportName($html));
    }

    public function testIgnoresAdHocDumpsAndOtherFiles(): void
    {
        $html = self::row('players_202607.zip', '11-Aug-2026 09:04')
            . self::row('players_20260814_001.zip', '14-Aug-2026 09:52')
            . self::row('players_202307-safet.zip', '20-Dec-2026 20:41')
            . self::row('players_SwissManager.csv', '21-Dec-2026 09:54');

        $this->assertSame('players_202607.zip', FrbeExportDownloader::newestExportName($html));
    }

    public function testComparesAcrossYearsAndMonthsChronologically(): void
    {
        // Lexical order of "dd-Mon-yyyy" would get both of these wrong.
        $html = self::row('players_202510.zip', '31-Dec-2025 23:00')
            . self::row('players_202601.zip', '03-Jan-2026 16:59');
        $this->assertSame('players_202601.zip', FrbeExportDownloader::newestExportName($html));

        $html = self::row('players_202601.zip', '30-Apr-2026 10:00')
            . self::row('players_202604.zip', '01-May-2026 10:00');
        $this->assertSame('players_202604.zip', FrbeExportDownloader::newestExportName($html));
    }

    public function testReturnsNullWithoutCanonicalExport(): void
    {
        $this->assertNull(FrbeExportDownloader::newestExportName('<html><body>Moved</body></html>'));
        $this->assertNull(FrbeExportDownloader::newestExportName(
            self::row('players_20260814_001.zip', '14-Aug-2026 09:52')
        ));
    }

    public function testFreshCacheIsReusedWithoutNetwork(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'jef-frbe-');
        try {
            // An unroutable base URL proves no request is made.
            $downloader = new FrbeExportDownloader($file, 3600, 'http://127.0.0.1:9/');
            $this->assertSame($file, $downloader->fetch());
        } finally {
            @unlink($file);
            @unlink($file . '.failed');
        }
    }

    public function testStaleCacheIsUsedWhenRefreshFails(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'jef-frbe-');
        touch($file, time() - 86400);
        try {
            $downloader = new FrbeExportDownloader($file, 3600, 'http://127.0.0.1:9/', 2);
            $this->assertSame($file, $downloader->fetch());
        } finally {
            @unlink($file);
            @unlink($file . '.failed');
        }
    }

    public function testTooOldCacheIsNotUsedWhenRefreshFails(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'jef-frbe-');
        touch($file, time() - 8 * 86400);
        try {
            $downloader = new FrbeExportDownloader($file, 3600, 'http://127.0.0.1:9/', 2);
            $this->expectExceptionMessage('too old to use');
            $downloader->fetch();
        } finally {
            @unlink($file);
            @unlink($file . '.failed');
        }
    }

    public function testThrowsWithoutCacheWhenUnreachable(): void
    {
        $file = sys_get_temp_dir() . '/jef-frbe-missing-' . bin2hex(random_bytes(4)) . '.sqlite';
        $downloader = new FrbeExportDownloader($file, 3600, 'http://127.0.0.1:9/', 2);

        try {
            $this->expectException(\RuntimeException::class);
            $downloader->fetch();
        } finally {
            @unlink($file . '.failed');
        }
    }

    public function testFailedDownloadIsNotRetriedUntilDelayPasses(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'jef-frbe-');
        touch($file, time() - 86400);
        try {
            $downloader = new FrbeExportDownloader($file, 3600, 'http://127.0.0.1:9/', 2, 1800);
            $downloader->fetch();
            $this->assertFileExists($file . '.failed');
            $this->assertStringContainsString('unreachable', (string) $downloader->refreshError());

            // Within the delay: stale copy, no download attempted.
            $this->assertSame($file, $downloader->fetch());
            $this->assertStringContainsString('not retrying yet', (string) $downloader->refreshError());

            // Delay over: it tries again (and fails again).
            touch($file . '.failed', time() - 3600);
            $downloader->fetch();
            $this->assertStringContainsString('unreachable', (string) $downloader->refreshError());

            // Forced: always tries.
            $downloader->fetch(force: true);
            $this->assertStringContainsString('unreachable', (string) $downloader->refreshError());
        } finally {
            @unlink($file);
            @unlink($file . '.failed');
        }
    }

    public function testRetryDelayWithoutCacheFailsFast(): void
    {
        $file = sys_get_temp_dir() . '/jef-frbe-missing-' . bin2hex(random_bytes(4)) . '.sqlite';
        touch($file . '.failed');
        try {
            $downloader = new FrbeExportDownloader($file, 3600, 'http://127.0.0.1:9/', 2, 1800);
            $this->expectExceptionMessage('not retrying yet');
            $downloader->fetch();
        } finally {
            @unlink($file . '.failed');
        }
    }
}
