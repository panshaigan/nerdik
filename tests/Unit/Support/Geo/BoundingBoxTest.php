<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Geo;

use App\Support\Geo\BoundingBox;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class BoundingBoxTest extends TestCase
{
    public function test_from_center_radius_km_at_equator(): void
    {
        $box = BoundingBox::fromCenterRadiusKm(0.0, 0.0, 55);

        $this->assertEqualsWithDelta(0.99, (float) $box['max_lat'] - (float) $box['min_lat'], 0.05);
        $this->assertEqualsWithDelta(0.99, (float) $box['max_lng'] - (float) $box['min_lng'], 0.05);
    }

    public function test_from_center_radius_km_near_pole_clamps_latitude(): void
    {
        $north = BoundingBox::fromCenterRadiusKm(89.5, 10.0, 200);
        $this->assertSame('90', $north['max_lat']);

        $south = BoundingBox::fromCenterRadiusKm(-89.5, 10.0, 200);
        $this->assertSame('-90', $south['min_lat']);
    }

    #[DataProvider('highLatitudeLongitudeSpanProvider')]
    public function test_longitude_span_widens_at_higher_latitude(float $lat, int $radiusKm): void
    {
        $equator = BoundingBox::fromCenterRadiusKm(0.0, 0.0, $radiusKm);
        $highLat = BoundingBox::fromCenterRadiusKm($lat, 0.0, $radiusKm);

        $equatorLngSpan = (float) $equator['max_lng'] - (float) $equator['min_lng'];
        $highLatLngSpan = (float) $highLat['max_lng'] - (float) $highLat['min_lng'];

        $this->assertGreaterThan($equatorLngSpan, $highLatLngSpan);
    }

    /**
     * @return array<string, array{float, int}>
     */
    public static function highLatitudeLongitudeSpanProvider(): array
    {
        return [
            'warsaw latitude' => [52.0, 25],
        ];
    }
}
