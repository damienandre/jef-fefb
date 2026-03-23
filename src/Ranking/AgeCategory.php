<?php

declare(strict_types=1);

namespace Jef\Ranking;

final class AgeCategory
{
    private const CATEGORIES = [
        ['max_age' => 8, 'label' => 'U8'],
        ['max_age' => 10, 'label' => 'U10'],
        ['max_age' => 12, 'label' => 'U12'],
        ['max_age' => 14, 'label' => 'U14'],
        ['max_age' => 16, 'label' => 'U16'],
        ['max_age' => 20, 'label' => 'U20'],
    ];

    /**
     * Determine the age category based on birth date and season year.
     * Age is calculated as of January 1st of the season year.
     *
     * Returns null if the player is 20 or older (not eligible).
     */
    public static function determine(\DateTimeImmutable $birthDate, int $seasonYear): ?string
    {
        $jan1 = new \DateTimeImmutable("{$seasonYear}-01-01");
        $age = (int) $birthDate->diff($jan1)->y;

        foreach (self::CATEGORIES as $category) {
            if ($age < $category['max_age']) {
                return $category['label'];
            }
        }

        return null;
    }

    /**
     * Return all valid category labels.
     */
    public static function all(): array
    {
        return array_column(self::CATEGORIES, 'label');
    }
}
