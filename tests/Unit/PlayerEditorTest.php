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

    public function testNoCollisionNoWarning(): void
    {
        $this->assertNull(PlayerEditor::collisionWarning(['fide_id' => null, 'birth_date' => '2017-01-01'], []));
    }

    public function testCollisionWarningRecommendsDryRun(): void
    {
        $warning = PlayerEditor::collisionWarning(
            ['fide_id' => 123, 'birth_date' => '2017-01-01'],
            [['id' => 7, 'fide_id' => null, 'shared_tournaments' => 0]]
        );

        $this->assertStringContainsString('#7', $warning);
        $this->assertStringContainsString('php cli/normalize-players.php --dry-run', $warning);
        $this->assertStringContainsString('tous les doublons', $warning);
    }

    public function testCollisionWarningSharedTournamentAdvisesAgainstMerge(): void
    {
        $warning = PlayerEditor::collisionWarning(
            ['fide_id' => null, 'birth_date' => '2017-01-01'],
            [
                ['id' => 7, 'fide_id' => null, 'shared_tournaments' => 0],
                ['id' => 8, 'fide_id' => null, 'shared_tournaments' => 2],
            ]
        );

        $this->assertStringContainsString('Le(s) joueur(s) #8 ont joué', $warning);
        $this->assertStringContainsString('Ne fusionnez pas', $warning);
        $this->assertStringNotContainsString('normalize-players', $warning);
    }

    public function testCollisionWarningMultipleFideIds(): void
    {
        $warning = PlayerEditor::collisionWarning(
            ['fide_id' => 123, 'birth_date' => '2017-01-01'],
            [['id' => 7, 'fide_id' => 456, 'shared_tournaments' => 0]]
        );

        $this->assertStringContainsString('ID FIDE différents', $warning);
        $this->assertStringNotContainsString('normalize-players', $warning);
    }

    public function testCollisionWarningNoBirthDateNoFide(): void
    {
        $warning = PlayerEditor::collisionWarning(
            ['fide_id' => null, 'birth_date' => null],
            [['id' => 7, 'fide_id' => null, 'shared_tournaments' => 0]]
        );

        $this->assertStringContainsString('Sans date de naissance ni ID FIDE', $warning);
        $this->assertStringNotContainsString('normalize-players', $warning);
    }
}
