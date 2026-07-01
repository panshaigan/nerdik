<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Livewire\Activities\ShowActivity;
use App\Models\Activity;
use App\Models\City;
use App\Models\CityTranslation;
use App\Models\Country;
use App\Models\Event;
use App\Models\Place;
use App\Models\Slot;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ShowActivityAboutTabTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_renders_event_venue_on_about_tab_when_slot_has_no_place(): void
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

        $html = Livewire::test(ShowActivity::class, ['activity' => $activity->fresh(['slot.event.places.city', 'slot.place'])])
            ->html();

        $this->assertStringContainsString('data-ui="activity-show-schedule-overlay"', $html);
        $this->assertStringContainsString('Convention Center', $html);
        $this->assertStringContainsString('1 Main Street', $html);
        $this->assertStringContainsString('Wroclaw', $html);
        $this->assertStringContainsString('data-ui="activity-show-schedule-map"', $html);

        Carbon::setTestNow();
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
