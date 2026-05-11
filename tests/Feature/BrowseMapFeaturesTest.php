<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Browse\BrowseEvents;
use App\Models\Activity;
use App\Models\Event;
use App\Models\Place;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BrowseMapFeaturesTest extends TestCase
{
    use RefreshDatabase;

    public function test_map_features_returns_422_without_bbox(): void
    {
        $this->getJson(route('search.map-features', ['zoom' => 10]))
            ->assertStatus(422)
            ->assertJsonPath('meta.invalidBBox', true);
    }

    public function test_map_features_returns_points_at_high_zoom(): void
    {
        $user = User::factory()->create();
        $startsAt = now()->addDays(14)->setSecond(0);
        $endsAt = (clone $startsAt)->addHours(5);

        $place = Place::factory()->venue()->create([
            'name' => 'Map Test Venue',
            'latitude' => 51.11,
            'longitude' => 17.03,
        ]);

        $event = Event::factory()->public()->create([
            'created_by' => $user->id,
            'name' => 'Map Features Event Unique',
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);
        $event->places()->attach($place->id);

        Activity::factory()->create([
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'place_id' => $place->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'name' => 'Map Features Activity Unique',
        ]);

        $res = $this->getJson(route('search.map-features', [
            'min_lat' => 51.0,
            'max_lat' => 51.2,
            'min_lng' => 16.9,
            'max_lng' => 17.2,
            'zoom' => 12,
        ]));
        $res->assertOk();
        $res->assertJsonPath('meta.clustered', false);
        $features = $res->json('features');
        $this->assertIsArray($features);
        $this->assertGreaterThanOrEqual(2, count($features));
        $kinds = array_values(array_filter(array_map(
            static fn (array $f): ?string => isset($f['properties']['kind']) ? (string) $f['properties']['kind'] : null,
            $features
        )));
        $this->assertContains('event', $kinds);
        $this->assertContains('activity', $kinds);
    }

    public function test_map_features_returns_clusters_at_low_zoom(): void
    {
        $user = User::factory()->create();
        $startsAt = now()->addDays(14)->setSecond(0);
        $endsAt = (clone $startsAt)->addHours(5);
        $place = Place::factory()->venue()->create([
            'latitude' => 51.11,
            'longitude' => 17.03,
        ]);
        $event = Event::factory()->public()->create([
            'created_by' => $user->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);
        $event->places()->attach($place->id);

        $res = $this->getJson(route('search.map-features', [
            'min_lat' => 51.0,
            'max_lat' => 51.2,
            'min_lng' => 16.9,
            'max_lng' => 17.2,
            'zoom' => 7,
        ]));
        $res->assertOk();
        $res->assertJsonPath('meta.clustered', true);
        $this->assertNotEmpty($res->json('features'));
        $this->assertTrue((bool) ($res->json('features.0.properties.cluster') ?? false));
    }

    public function test_map_features_rejects_oversized_bbox(): void
    {
        $res = $this->getJson(route('search.map-features', [
            'min_lat' => -60,
            'max_lat' => 60,
            'min_lng' => -80,
            'max_lng' => 80,
            'zoom' => 10,
        ]));
        $res->assertOk();
        $res->assertJsonPath('meta.bboxTooLarge', true);
        $this->assertSame([], $res->json('features'));
    }

    public function test_browse_events_map_view_toggle_renders_map_root(): void
    {
        Livewire::test(BrowseEvents::class)
            ->set('map_view', true)
            ->assertSee('data-browse-events-map', false);
    }

    public function test_browse_events_toggle_map_view_action_flips_state(): void
    {
        Livewire::test(BrowseEvents::class)
            ->assertSet('map_view', false)
            ->call('toggleMapView')
            ->assertSet('map_view', true)
            ->call('toggleMapView')
            ->assertSet('map_view', false);
    }
}
