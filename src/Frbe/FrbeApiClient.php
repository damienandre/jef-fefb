<?php

declare(strict_types=1);

namespace Jef\Frbe;

/**
 * HTTP implementation of {@see FrbeApi} against the FRBE/KBSB public API.
 *
 * Uses the cURL extension with a short timeout so a slow or unreachable API
 * never stalls an import for long (enrichment is always best-effort). A missing
 * record (HTTP 404) or an empty/zero body resolves to null rather than throwing.
 */
final class FrbeApiClient implements FrbeApi
{
    public const DEFAULT_BASE_URL = 'https://www.frbe-kbsb-ksb.be/api/v1';

    private string $baseUrl;

    public function __construct(?string $baseUrl = null, private int $timeoutSeconds = 5)
    {
        $this->baseUrl = rtrim($baseUrl ?? self::DEFAULT_BASE_URL, '/');
    }

    public function listClubs(): array
    {
        $data = $this->getJson('/clubs/anon/club');
        if (!is_array($data)) {
            return [];
        }

        $clubs = [];
        foreach ($data as $row) {
            if (!is_array($row) || !isset($row['idclub'])) {
                continue;
            }
            $clubs[] = [
                'idclub' => (int) $row['idclub'],
                'name_long' => (string) ($row['name_long'] ?? ''),
                'name_short' => isset($row['name_short']) && $row['name_short'] !== ''
                    ? (string) $row['name_short']
                    : null,
                'enabled' => (bool) ($row['enabled'] ?? true),
            ];
        }

        return $clubs;
    }

    public function fideIdToBelgianId(int $fideId): ?int
    {
        $data = $this->getJson('/member/anon/fideid2belid/' . $fideId);
        // Endpoint returns a bare integer; 0 means "no Belgian membership".
        if (!is_int($data) && !is_string($data)) {
            return null;
        }
        $belId = (int) $data;

        return $belId > 0 ? $belId : null;
    }

    public function getMember(int $belgianId): ?array
    {
        $data = $this->getJson('/member/anon/member/' . $belgianId);

        return is_array($data) ? $data : null;
    }

    public function getFideMember(int $fideId): ?array
    {
        $data = $this->getJson('/member/anon/fidemember/' . $fideId);

        return is_array($data) ? $data : null;
    }

    /**
     * GET a path and JSON-decode the body. Returns null on HTTP >= 400,
     * transport error, or undecodable body.
     */
    private function getJson(string $path): mixed
    {
        $ch = curl_init($this->baseUrl . $path);
        if ($ch === false) {
            return null;
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_CONNECTTIMEOUT => $this->timeoutSeconds,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
            CURLOPT_FOLLOWLOCATION => true,
        ]);

        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        // No curl_close(): handles are auto-freed objects since PHP 8.0 and the
        // function is deprecated in 8.5.

        if ($body === false || $status >= 400 || $body === '') {
            return null;
        }

        return json_decode((string) $body, true);
    }
}
