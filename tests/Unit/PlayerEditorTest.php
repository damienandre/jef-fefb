<?php

declare(strict_types=1);

namespace Tests\Unit;

use Jef\PlayerEditor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PlayerEditorTest extends TestCase
{
    private static function pdoException(string $sqlState, ?int $driverCode, string $message): \PDOException
    {
        $e = new \PDOException("SQLSTATE[{$sqlState}]: {$message}");
        $e->errorInfo = [$sqlState, $driverCode, $message];
        return $e;
    }

    /** @return array<string, array{\PDOException, ?string}> */
    public static function duplicateKeyCases(): array
    {
        return [
            'fide id' => [
                self::pdoException('23000', 1062, "Duplicate entry '123' for key 'jef_players.uk_fide_id'"),
                'Cet ID FIDE est déjà attribué à un autre joueur.',
            ],
            'belgian id' => [
                self::pdoException('23000', 1062, "Duplicate entry '456' for key 'jef_players.uk_belgian_id'"),
                'Cet ID belge est déjà attribué à un autre joueur.',
            ],
            'other unique key' => [
                self::pdoException('23000', 1062, "Duplicate entry 'x' for key 'jef_players.uk_other'"),
                null,
            ],
            'not a duplicate key' => [
                self::pdoException('23000', 1452, 'Cannot add or update a child row: a foreign key constraint fails'),
                null,
            ],
            'no error info' => [
                new \PDOException('connection lost'),
                null,
            ],
        ];
    }

    #[DataProvider('duplicateKeyCases')]
    public function testDuplicateKeyMessage(\PDOException $e, ?string $expected): void
    {
        $this->assertSame($expected, PlayerEditor::duplicateKeyMessage($e));
    }
}
