<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Ui;

use App\Models\Activity;
use App\Models\City;
use App\Models\CityTranslation;
use App\Models\Country;
use App\Models\Event;
use App\Models\Place;
use App\Models\Slot;
use App\Models\User;
use App\Support\Ui\ActivityShowSchedulePresenter;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ActivityShowSchedulePresenterTest extends TestCase
{
    use RefreshDatabase;

    private ActivityShowSchedulePresenter $presenter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->presenter = new ActivityShowSchedulePresenter;
    }

    #[Test]
    public function build_falls_back_to_event_venue_when_slot_has_no_place(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-15 12:00:00', 'UTC'));

        $user = User::factory()->create();
        $city = $this->createCity('Wroclaw');
        $venue = Place::factory()->venue()->poland()->create([
            'name' => 'Convention Center',
            'address' => '1 Main Street',
            'city_id' => $city->id,
        ]);

        $event = Event::factory()->create(['created_by' => $user->id]);
        $event->places()->attach($venue->id);

        $startsAt = now()->addDay()->setTime(10, 0);
        $endsAt = (clone $startsAt)->setTime(12, 0);

        $activity = Activity::factory()->scheduled()->create([
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        Slot::factory()->create([
            'event_id' => $event->id,
            'activity_id' => $activity->id,
            'place_id' => null,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);

        $schedule = $this->presenter->build($activity->fresh(['slot.event.places.city', 'slot.place']));

        $this->assertSame('Convention Center', $schedule->scheduleVenue?->name);
        $this->assertNull($schedule->scheduleRoom);
        $this->assertNull($schedule->schedulePlaceSummary);
        $this->assertNotNull($schedule->scheduleDateSummary);
        $this->assertCount(1, $schedule->scheduleMapConfig['places']);
        $this->assertSame('Convention Center', $schedule->scheduleMapConfig['places'][0]['name']);

        Carbon::setTestNow();
    }

    #[Test]
    public function build_uses_slot_place_when_present(): void
    {
        $user = User::factory()->create();
        Country::query()->create(['iso_alpha2' => 'PL']);
        $venue = Place::factory()->venue()->poland()->create(['name' => 'Convention Center']);
        $room = Place::factory()->room($venue)->create(['name' => 'Hall A']);
        $event = Event::factory()->create(['created_by' => $user->id]);

        $activity = Activity::factory()->scheduled()->create([
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        Slot::factory()->create([
            'event_id' => $event->id,
            'activity_id' => $activity->id,
            'place_id' => $room->id,
            'starts_at' => now()->addDay()->setTime(10, 0),
            'ends_at' => now()->addDay()->setTime(12, 0),
        ]);

        $schedule = $this->presenter->build($activity->fresh(['slot.place.parent', 'slot.event.places']));

        $this->assertSame('Convention Center', $schedule->scheduleVenue?->name);
        $this->assertSame('Hall A', $schedule->scheduleRoom);
    }

    #[Test]
    public function build_uses_compact_place_summary_for_multiple_event_venues_without_slot_place(): void
    {
        $user = User::factory()->create();
        $city = $this->createCity('Wroclaw');
        $firstVenue = Place::factory()->venue()->poland()->create([
            'name' => 'North Hall',
            'city_id' => $city->id,
        ]);
        $secondVenue = Place::factory()->venue()->poland()->create([
            'name' => 'South Hall',
            'city_id' => $city->id,
        ]);

        $event = Event::factory()->create(['created_by' => $user->id]);
        $event->places()->attach([$firstVenue->id, $secondVenue->id]);

        $activity = Activity::factory()->scheduled()->create([
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        Slot::factory()->create([
            'event_id' => $event->id,
            'activity_id' => $activity->id,
            'place_id' => null,
            'starts_at' => now()->addDay()->setTime(10, 0),
            'ends_at' => now()->addDay()->setTime(12, 0),
        ]);

        $schedule = $this->presenter->build($activity->fresh(['slot.event.places.city', 'slot.place']));

        $this->assertNull($schedule->scheduleVenue);
        $this->assertSame('North Hall, South Hall (Wroclaw)', $schedule->schedulePlaceSummary);
        $this->assertCount(2, $schedule->scheduleMapConfig['places']);
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
