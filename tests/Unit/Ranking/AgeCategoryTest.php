<?php

declare(strict_types=1);

namespace Tests\Unit\Ranking;

use Jef\Ranking\AgeCategory;
use PHPUnit\Framework\TestCase;

final class AgeCategoryTest extends TestCase
{
    public function testU8PlayerAge7OnJan1(): void
    {
        // Born 2019-03-15, season 2026: age on Jan 1 2026 = 6
        $this->assertSame('U8', AgeCategory::determine(new \DateTimeImmutable('2019-03-15'), 2026));
    }

    public function testU8BoundaryExactly7OnJan1(): void
    {
        // Born 2019-01-01, season 2026: age on Jan 1 2026 = 7
        $this->assertSame('U8', AgeCategory::determine(new \DateTimeImmutable('2019-01-01'), 2026));
    }

    public function testU10PlayerAge8OnJan1(): void
    {
        // Born 2018-01-01, season 2026: age on Jan 1 2026 = 8
        $this->assertSame('U10', AgeCategory::determine(new \DateTimeImmutable('2018-01-01'), 2026));
    }

    public function testU10PlayerAge9OnJan1(): void
    {
        // Born 2016-06-15, season 2026: age on Jan 1 2026 = 9
        $this->assertSame('U10', AgeCategory::determine(new \DateTimeImmutable('2016-06-15'), 2026));
    }

    public function testU12PlayerAge10OnJan1(): void
    {
        // Born 2016-01-01, season 2026: age on Jan 1 2026 = 10
        $this->assertSame('U12', AgeCategory::determine(new \DateTimeImmutable('2016-01-01'), 2026));
    }

    public function testU12PlayerAge11OnJan1(): void
    {
        // Born 2014-12-31, season 2026: age on Jan 1 2026 = 11
        $this->assertSame('U12', AgeCategory::determine(new \DateTimeImmutable('2014-12-31'), 2026));
    }

    public function testU14PlayerAge12OnJan1(): void
    {
        // Born 2014-01-01, season 2026: age on Jan 1 2026 = 12
        $this->assertSame('U14', AgeCategory::determine(new \DateTimeImmutable('2014-01-01'), 2026));
    }

    public function testU14PlayerAge13OnJan1(): void
    {
        // Born 2012-07-20, season 2026: age on Jan 1 2026 = 13
        $this->assertSame('U14', AgeCategory::determine(new \DateTimeImmutable('2012-07-20'), 2026));
    }

    public function testU16PlayerAge14OnJan1(): void
    {
        // Born 2012-01-01, season 2026: age on Jan 1 2026 = 14
        $this->assertSame('U16', AgeCategory::determine(new \DateTimeImmutable('2012-01-01'), 2026));
    }

    public function testU16PlayerAge15OnJan1(): void
    {
        // Born 2010-11-30, season 2026: age on Jan 1 2026 = 15
        $this->assertSame('U16', AgeCategory::determine(new \DateTimeImmutable('2010-11-30'), 2026));
    }

    public function testU20PlayerAge16OnJan1(): void
    {
        // Born 2010-01-01, season 2026: age on Jan 1 2026 = 16
        $this->assertSame('U20', AgeCategory::determine(new \DateTimeImmutable('2010-01-01'), 2026));
    }

    public function testU20PlayerAge19OnJan1(): void
    {
        // Born 2007-01-01, season 2026: age on Jan 1 2026 = 19
        $this->assertSame('U20', AgeCategory::determine(new \DateTimeImmutable('2007-01-01'), 2026));
    }

    public function testPlayerAge20OnJan1ReturnsNull(): void
    {
        // Born 2006-01-01, season 2026: age on Jan 1 2026 = 20
        $this->assertNull(AgeCategory::determine(new \DateTimeImmutable('2006-01-01'), 2026));
    }

    public function testBornDec31BoundaryU8ToU10(): void
    {
        // Born 2017-12-31, season 2026: age on Jan 1 2026 = 8 → U10
        $this->assertSame('U10', AgeCategory::determine(new \DateTimeImmutable('2017-12-31'), 2026));
    }

    public function testBornJan2BoundaryU8(): void
    {
        // Born 2019-01-02, season 2026: age on Jan 1 2026 = 6 → U8
        $this->assertSame('U8', AgeCategory::determine(new \DateTimeImmutable('2019-01-02'), 2026));
    }
}
