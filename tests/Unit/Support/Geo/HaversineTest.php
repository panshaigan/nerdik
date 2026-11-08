<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Geo;

use App\Support\Geo\Haversine;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class HaversineTest extends TestCase
{
    public function test_circles_overlap_when_centers_are_close(): void
    {
        $this->assertTrue(Haversine::circlesOverlapKm(52.2297, 21.0122, 25, 52.24, 21.02, 25));
    }

    public function test_circles_do_not_overlap_when_centers_are_far(): void
    {
        $this->assertFalse(Haversine::circlesOverlapKm(52.2297, 21.0122, 10, 50.0, 14.0, 10));
    }

    #[DataProvider('distanceKnownPointsProvider')]
    public function test_distance_km_between_known_points(float $expectedKm, float $latA, float $lngA, float $latB, float $lngB): void
    {
        $distance = Haversine::distanceKm($latA, $lngA, $latB, $lngB);

        $this->assertEqualsWithDelta($expectedKm, $distance, 5.0);
    }

    /**
     * @return array<string, array{float, float, float, float, float}>
     */
    public static function distanceKnownPointsProvider(): array
    {
        return [
            'warsaw to krakow approx' => [252.0, 52.2297, 21.0122, 50.0647, 19.9450],
        ];
    }
}
