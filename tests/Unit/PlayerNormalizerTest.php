<?php

declare(strict_types=1);

namespace Tests\Unit;

use Jef\PlayerNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PlayerNormalizerTest extends TestCase
{
    /** @return array<string, array{list<array{fide_id:?int, birth_date:?string}>, ?string}> */
    public static function clusters(): array
    {
        return [
            'birth date, no FIDE: merged' => [
                [['fide_id' => null, 'birth_date' => '2017-01-01'], ['fide_id' => null, 'birth_date' => '2017-01-01']],
                null,
            ],
            'one FIDE id: merged' => [
                [['fide_id' => 123, 'birth_date' => '2017-01-01'], ['fide_id' => null, 'birth_date' => '2017-01-01']],
                null,
            ],
            'several FIDE ids: skipped' => [
                [['fide_id' => 123, 'birth_date' => '2017-01-01'], ['fide_id' => 456, 'birth_date' => '2017-01-01']],
                PlayerNormalizer::SKIP_MULTIPLE_FIDE,
            ],
            'no birth date, no FIDE: skipped' => [
                [['fide_id' => null, 'birth_date' => null], ['fide_id' => null, 'birth_date' => null]],
                PlayerNormalizer::SKIP_NO_DOB_NO_FIDE,
            ],
            'no birth date, one FIDE id: merged' => [
                [['fide_id' => 123, 'birth_date' => null], ['fide_id' => null, 'birth_date' => null]],
                null,
            ],
        ];
    }

    #[DataProvider('clusters')]
    public function testMergeSkipRule(array $rows, ?string $expected): void
    {
        $this->assertSame($expected, PlayerNormalizer::mergeSkipRule($rows));
    }
}
