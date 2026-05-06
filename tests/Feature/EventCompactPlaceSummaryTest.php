<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\CityTranslation;
use App\Models\Country;
use App\Models\Event;
use App\Models\Place;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EventCompactPlaceSummaryTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_shows_city_once_when_all_venues_are_in_the_same_city(): void
    {
        $event = Event::factory()->create([
            'created_by' => User::factory()->create()->id,
        ]);
        $country = Country::query()->create(['iso_alpha2' => 'PL']);
        $city = City::factory()->create([
            'country_id' => $country->id,
        ]);
        CityTranslation::query()->create([
            'city_id' => $city->id,
            'locale' => app()->getLocale(),
            'name' => 'Wroclaw',
        ]);

        $firstVenue = Place::factory()->venue()->create([
            'name' => 'Venue One',
            'city_id' => $city->id,
        ]);
        $secondVenue = Place::factory()->venue()->create([
            'name' => 'Venue Two',
            'city_id' => $city->id,
        ]);

        $event->places()->attach([$firstVenue->id, $secondVenue->id]);
        $event->load('places.city');

        $this->assertSame('Venue One, Venue Two (Wroclaw)', $event->compactPlaceSummary());
    }

    #[Test]
    public function it_includes_city_per_venue_when_venues_are_in_different_cities(): void
    {
        $event = Event::factory()->create([
            'created_by' => User::factory()->create()->id,
        ]);

        $country = Country::query()->create(['iso_alpha2' => 'PL']);
        $firstCity = City::factory()->create([
            'country_id' => $country->id,
        ]);
        CityTranslation::query()->create([
            'city_id' => $firstCity->id,
            'locale' => app()->getLocale(),
            'name' => 'Warsaw',
        ]);

        $secondCity = City::factory()->create([
            'country_id' => $country->id,
        ]);
        CityTranslation::query()->create([
            'city_id' => $secondCity->id,
            'locale' => app()->getLocale(),
            'name' => 'Krakow',
        ]);

        $firstVenue = Place::factory()->venue()->create([
            'name' => 'Venue One',
            'city_id' => $firstCity->id,
        ]);
        $secondVenue = Place::factory()->venue()->create([
            'name' => 'Venue Two',
            'city_id' => $secondCity->id,
        ]);

        $event->places()->attach([$firstVenue->id, $secondVenue->id]);
        $event->load('places.city');

        $this->assertSame('Venue One (Warsaw), Venue Two (Krakow)', $event->compactPlaceSummary());
    }
}
