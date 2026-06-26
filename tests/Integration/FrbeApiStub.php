<?php

declare(strict_types=1);

namespace Tests\Integration;

use Jef\Frbe\FrbeApi;

/**
 * In-memory {@see FrbeApi} for tests, so the enricher and import path can run
 * without any network. A default-constructed stub knows nothing and therefore
 * enriches nothing.
 */
final class FrbeApiStub implements FrbeApi
{
    /**
     * @param list<array{idclub:int, name_long:string, name_short:?string, enabled:bool}> $clubs
     * @param array<int, int>   $fideToBel    fide_id => belgian_id
     * @param array<int, array> $members      belgian_id => member record
     * @param array<int, array> $fideMembers  fide_id => member record (FIDE-keyed fallback)
     */
    public function __construct(
        private array $clubs = [],
        private array $fideToBel = [],
        private array $members = [],
        private array $fideMembers = [],
    ) {}

    public function listClubs(): array
    {
        return $this->clubs;
    }

    public function fideIdToBelgianId(int $fideId): ?int
    {
        return $this->fideToBel[$fideId] ?? null;
    }

    public function getMember(int $belgianId): ?array
    {
        return $this->members[$belgianId] ?? null;
    }

    public function getFideMember(int $fideId): ?array
    {
        if (isset($this->fideMembers[$fideId])) {
            return $this->fideMembers[$fideId];
        }
        $belId = $this->fideToBel[$fideId] ?? null;
        return $belId !== null ? ($this->members[$belId] ?? null) : null;
    }
}
