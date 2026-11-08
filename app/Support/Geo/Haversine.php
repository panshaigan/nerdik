<?php

declare(strict_types=1);

namespace App\Support\Geo;

/**
 * Great-circle distance on WGS84 (spherical approximation).
 */
final class Haversine
{
    public const float EARTH_RADIUS_KM = 6371.0;

    public static function distanceKm(
        float $latitudeFrom,
        float $longitudeFrom,
        float $latitudeTo,
        float $longitudeTo,
    ): float {
        $latFrom = deg2rad($latitudeFrom);
        $latTo = deg2rad($latitudeTo);
        $latDelta = deg2rad($latitudeTo - $latitudeFrom);
        $lngDelta = deg2rad($longitudeTo - $longitudeFrom);

        $a = sin($latDelta / 2) ** 2
            + cos($latFrom) * cos($latTo) * sin($lngDelta / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return self::EARTH_RADIUS_KM * $c;
    }

    public static function circlesOverlapKm(
        float $latitudeA,
        float $longitudeA,
        int $radiusKmA,
        float $latitudeB,
        float $longitudeB,
        int $radiusKmB,
    ): bool {
        $distance = self::distanceKm($latitudeA, $longitudeA, $latitudeB, $longitudeB);

        return $distance <= ($radiusKmA + $radiusKmB);
    }
}
