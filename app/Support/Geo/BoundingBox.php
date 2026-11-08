<?php

declare(strict_types=1);

namespace App\Support\Geo;

/**
 * Axis-aligned bounding box from a center point and radius (no PostGIS).
 */
final class BoundingBox
{
    /** Kilometers per degree latitude (WGS84 mean). */
    private const float KM_PER_DEGREE_LAT = 111.32;

    /**
     * @return array{min_lat: string, max_lat: string, min_lng: string, max_lng: string}
     */
    public static function fromCenterRadiusKm(float $latitude, float $longitude, int $radiusKm): array
    {
        $deltaLat = $radiusKm / self::KM_PER_DEGREE_LAT;

        $cosLat = cos(deg2rad($latitude));
        $kmPerDegreeLng = self::KM_PER_DEGREE_LAT * max(abs($cosLat), 1e-6);
        $deltaLng = $radiusKm / $kmPerDegreeLng;

        $minLat = max(-90.0, $latitude - $deltaLat);
        $maxLat = min(90.0, $latitude + $deltaLat);
        $minLng = self::normalizeLongitude($longitude - $deltaLng);
        $maxLng = self::normalizeLongitude($longitude + $deltaLng);

        return [
            'min_lat' => self::formatCoord($minLat),
            'max_lat' => self::formatCoord($maxLat),
            'min_lng' => self::formatCoord($minLng),
            'max_lng' => self::formatCoord($maxLng),
        ];
    }

    private static function normalizeLongitude(float $lng): float
    {
        while ($lng < -180.0) {
            $lng += 360.0;
        }
        while ($lng > 180.0) {
            $lng -= 360.0;
        }

        return $lng;
    }

    private static function formatCoord(float $value): string
    {
        return rtrim(rtrim(sprintf('%.8F', $value), '0'), '.');
    }
}
