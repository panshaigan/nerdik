<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\City;
use App\Models\CityTranslation;
use App\Models\Country;
use App\Models\Place;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PlaceCompactVenueSummaryTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_formats_venue_with_city_in_brackets(): void
    {
        $city = $this->createCity('Wroclaw');
        $venue = Place::factory()->venue()->create([
            'name' => 'Convention Center',
            'city_id' => $city->id,
        ]);

        $this->assertSame('Convention Center (Wroclaw)', $venue->compactVenueSummary());
    }

    #[Test]
    public function it_returns_venue_name_only_when_city_is_missing(): void
    {
        $venue = Place::factory()->venue()->create([
            'name' => 'Tavern Hall',
            'city_id' => null,
        ]);

        $this->assertSame('Tavern Hall', $venue->compactVenueSummary());
    }

    #[Test]
    public function it_uses_parent_venue_name_and_city_for_rooms(): void
    {
        $city = $this->createCity('Wroclaw');
        $venue = Place::factory()->venue()->create([
            'name' => 'Convention Center',
            'city_id' => $city->id,
        ]);
        $room = Place::factory()->room($venue)->create(['name' => 'Hall A']);

        $this->assertSame('Convention Center (Wroclaw)', $room->compactVenueSummary());
    }

    private function createCity(string $name): City
    {
        $country = Country::query()->create(['iso_alpha2' => 'PL']);
        $city = City::factory()->create([
            'country_id' => $country->id,
        ]);
        CityTranslation::query()->create([
            'city_id' => $city->id,
            'locale' => app()->getLocale(),
            'name' => $name,
        ]);

        return $city;
    }
}
