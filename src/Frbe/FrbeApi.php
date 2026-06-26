<?php

declare(strict_types=1);

namespace Jef\Frbe;

/**
 * Read-only access to the FRBE/KBSB public REST API
 * (https://www.frbe-kbsb-ksb.be/api/v1). Defined as an interface so tests can
 * stub the network with canned responses.
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
     * Returns null when the player is unknown (HTTP 404 or a 0/empty body).
     */
    public function fideIdToBelgianId(int $fideId): ?int;

    /**
     * Member record keyed by Belgian member id. Returns null on HTTP 404.
     *
     * @return array{idnumber:int, idfide:int, idclub:int, fiderating:int, natrating:int, first_name:string, last_name:string, birthyear:int}|null
     */
    public function getMember(int $belgianId): ?array;

    /**
     * Member record keyed by FIDE id (fallback). Returns null on HTTP 404.
     *
     * @return array{idnumber:int, idfide:int, idclub:int, fiderating:int, natrating:int, first_name:string, last_name:string, birthyear:int}|null
     */
    public function getFideMember(int $fideId): ?array;
}
