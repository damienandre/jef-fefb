<?php

declare(strict_types=1);

namespace Jef\Trf;

final class TrfTournament
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $city = null,
        public readonly ?string $federation = null,
        public readonly ?string $dateStart = null,
        public readonly ?string $dateEnd = null,
        public readonly int $playerCount = 0,
        public readonly int $roundCount = 0,
        public readonly ?string $arbiter = null,
        public readonly ?string $timeControl = null,
        public readonly array $roundDates = [],
    ) {}
}
