<?php

declare(strict_types=1);

namespace Jef\Trf;

final class TrfPlayer
{
    public function __construct(
        public readonly int $startingRank,
        public readonly ?string $sex = null,
        public readonly ?string $title = null,
        public readonly string $lastName = '',
        public readonly string $firstName = '',
        public readonly ?int $fideRating = null,
        public readonly ?string $federation = null,
        public readonly ?int $fideId = null,
        public readonly ?string $birthDate = null,
        public readonly float $points = 0.0,
        public readonly ?int $rank = null,
        public readonly array $rounds = [],
    ) {}
}
