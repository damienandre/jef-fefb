<?php

declare(strict_types=1);

namespace Tests\Unit\Frbe;

use Jef\Frbe\FrbeExport;
use PHPUnit\Framework\TestCase;

final class FrbeExportTest extends TestCase
{
    private string $file;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite not available');
        }
        $this->file = tempnam(sys_get_temp_dir(), 'jef-frbe-');
        $pdo = new \PDO('sqlite:' . $this->file);
        // Same shape as the real export (only the columns we read, plus Elo).
        $pdo->exec('CREATE TABLE players (IdNumber INT NOT NULL PRIMARY KEY, Name VARCHAR(32),
            Birthday VARCHAR(10), Club INT, Affiliated INT(1), Elo INT, G INT(1), FideId INT)');
        $pdo->exec('CREATE TABLE clubs (Club INT NOT NULL PRIMARY KEY, Name VARCHAR(100), Federation VARCHAR(20))');
        $pdo->exec("INSERT INTO clubs VALUES (108, 'Kon. SK Merksem', 'VSF'), (909, '', 'FEFB')");
        $pdo->exec("INSERT INTO players VALUES
            (12345, 'Dupont, Marie', '2012', 909, 1, NULL, 0, 205168),
            (-29740, 'Provisoire, Paul', '2014', 726, 0, NULL, 1, 548034045),
            (29740, 'Michiels, Senne', '2011', 108, 1, NULL, 0, 0),
            (-500, 'Twin, Tom', '2013', 108, 0, NULL, 1, 777),
            (501, 'Twin, Tom', '2013', 109, 1, NULL, 0, 777),
            (602, 'Lapsed, Lea', '2010', 412, 0, NULL, 0, 888)");
    }

    protected function tearDown(): void
    {
        if (isset($this->file)) {
            @unlink($this->file);
        }
    }

    public function testListClubs(): void
    {
        $clubs = FrbeExport::fromFile($this->file)->listClubs();

        $this->assertSame([
            ['idclub' => 108, 'name_long' => 'Kon. SK Merksem', 'name_short' => null, 'enabled' => true],
            // Empty name falls back to the number (name_long is NOT NULL).
            ['idclub' => 909, 'name_long' => '909', 'name_short' => null, 'enabled' => true],
        ], $clubs);
    }

    public function testResolvesFideIdAndMembers(): void
    {
        $export = FrbeExport::fromFile($this->file);

        $this->assertSame(12345, $export->fideIdToBelgianId(205168));
        $this->assertNull($export->fideIdToBelgianId(999999));
        $this->assertNull($export->fideIdToBelgianId(0)); // FideId 0 means "none"

        $member = $export->getMember(12345);
        $this->assertSame(909, $member['idclub']);
        $this->assertSame('Marie', $member['first_name']);
        $this->assertSame('Dupont', $member['last_name']);
        $this->assertSame(2012, $member['birthyear']);
        $this->assertSame(205168, $member['idfide']);
        $this->assertNull($export->getMember(1));

        $this->assertSame(12345, $export->getFideMember(205168)['idnumber']);
    }

    public function testProvisionalRecordGivesClubButNoBelgianId(): void
    {
        $export = FrbeExport::fromFile($this->file);

        // A G-licence id is negative and gets replaced: never persist it.
        $this->assertNull($export->fideIdToBelgianId(548034045));
        $member = $export->getFideMember(548034045);
        $this->assertTrue($member['provisional']);
        $this->assertSame(726, $member['idclub']);
        // Its magnitude is a different, real player's id.
        $this->assertSame('Michiels', $export->getMember(29740)['last_name']);
        $this->assertNull($export->getMember(-29740));
    }

    public function testLapsedMemberHasNoCurrentClub(): void
    {
        $export = FrbeExport::fromFile($this->file);

        // Still a known player with a stable id...
        $this->assertSame(602, $export->fideIdToBelgianId(888));
        // ...but the club the export remembers is not a current one.
        $this->assertSame(0, $export->getMember(602)['idclub']);
        $this->assertSame(0, $export->getFideMember(888)['idclub']);
        $this->assertFalse($export->getMember(602)['affiliated']);
    }

    public function testDefinitiveRecordWinsOverProvisionalForSameFideId(): void
    {
        $export = FrbeExport::fromFile($this->file);

        $this->assertSame(501, $export->fideIdToBelgianId(777));
        $this->assertSame(109, $export->getFideMember(777)['idclub']);
    }

    public function testUnavailableExportResolvesToNothing(): void
    {
        $export = new FrbeExport(static fn(): string => throw new \RuntimeException('offline'));

        $this->assertSame([], $export->listClubs());
        $this->assertNull($export->fideIdToBelgianId(205168));
        $this->assertNull($export->getMember(12345));
        $this->assertNull($export->getFideMember(205168));
        $this->assertSame('offline', $export->error());
    }

    public function testNotAnExportFileReportsError(): void
    {
        file_put_contents($this->file, '<html>blog</html>');
        $export = FrbeExport::fromFile($this->file);

        $this->assertNull($export->getMember(12345));
        $this->assertNotNull($export->error());
    }

    public function testDoesNotOpenTheFileUntilNeeded(): void
    {
        $calls = 0;
        $file = $this->file;
        $export = new FrbeExport(static function () use (&$calls, $file): string {
            $calls++;
            return $file;
        });
        $this->assertSame(0, $calls);
        $export->getMember(12345);
        $export->getFideMember(205168);
        $this->assertSame(1, $calls);
    }
}
