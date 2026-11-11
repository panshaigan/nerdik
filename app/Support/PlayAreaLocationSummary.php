<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\City;

final class PlayAreaLocationSummary
{
    public static function line(?string $street, ?City $city, ?string $locale = null): ?string
    {
        $street = $street !== null ? trim($street) : '';
        $cityName = $city?->name($locale);

        $parts = array_values(array_filter([
            $street !== '' ? $street : null,
            filled($cityName) ? $cityName : null,
        ]));

        if ($parts === []) {
            return null;
        }

        return implode(', ', $parts);
    }
}
