<?php

declare(strict_types=1);

namespace Jef\Frbe;

/**
 * Read-only access to Belgian federation (FRBE/KBSB) player and club data.
 * Implemented by {@see FrbeExport} from the federation's bulk player export
 * (the former REST member API was decommissioned in August 2026). Defined as an
 * interface so tests can stub it with canned data.
 */
interface FrbeApi
{
    /**
     * List of all clubs.
     *
     * @return list<array{idclub:int, name_long:string, name_short:?string, enabled:bool}>
     */
    public function listClubs(): array;

    /**
     * Resolve a FIDE id to a Belgian federation member id.
     * Returns null when the player is unknown or holds only a provisional
     * (G-licence) record, whose id is not stable.
     */
    public function fideIdToBelgianId(int $fideId): ?int;

    /**
     * Member record keyed by Belgian member id. Returns null when unknown.
     * idclub is 0 when the member has no club.
     *
     * @return array{idnumber:int, idfide:int, idclub:int, first_name:string, last_name:string, birthyear:int, affiliated:bool, provisional:bool}|null
     */
    public function getMember(int $belgianId): ?array;

    /**
     * Member record keyed by FIDE id (fallback). Returns null when unknown.
     *
     * @return array{idnumber:int, idfide:int, idclub:int, first_name:string, last_name:string, birthyear:int, affiliated:bool, provisional:bool}|null
     */
    public function getFideMember(int $fideId): ?array;
}
