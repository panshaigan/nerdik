<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Catalog;

use App\Models\Activity;
use App\Models\ActivitySeries;
use App\Models\ActivityUser;
use App\Models\Event;
use App\Models\EventSeries;
use App\Models\Slot;
use App\Models\User;
use App\Support\Catalog\CatalogSeriesSortStats;
use Database\Seeders\ActivityTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class CatalogSeriesSortStatsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_counts_catalog_visible_activity_series_stats_with_unique_participants_including_hosts(): void
    {
        Cache::forget(CatalogSeriesSortStats::CACHE_KEY);
        $this->seed(ActivityTypeSeeder::class);

        $host = User::factory()->create();
        $participant = User::factory()->create();
        $absent = User::factory()->create();

        $visibleSeries = ActivitySeries::factory()->create(['created_by' => $host->id]);
        $onRoster = Activity::factory()->selfHosted()->create([
            'created_by' => $host->id,
            'activity_series_id' => $visibleSeries->id,
        ]);
        $onRoster->update([
            'starts_at' => now()->addDays(3),
            'ends_at' => now()->addDays(3)->addHours(2),
        ]);

        $hostOnly = Activity::factory()->selfHosted()->create([
            'created_by' => $host->id,
            'activity_series_id' => $visibleSeries->id,
        ]);
        $hostOnly->update([
            'starts_at' => now()->addDays(4),
            'ends_at' => now()->addDays(4)->addHours(2),
        ]);

        ActivityUser::query()->create([
            'activity_id' => $onRoster->id,
            'user_id' => $host->id,
            'is_absent' => false,
        ]);
        ActivityUser::query()->create([
            'activity_id' => $onRoster->id,
            'user_id' => $participant->id,
            'is_absent' => false,
        ]);
        ActivityUser::query()->create([
            'activity_id' => $onRoster->id,
            'user_id' => $absent->id,
            'is_absent' => true,
        ]);

        $draftSeries = ActivitySeries::factory()->create(['created_by' => $host->id]);
        Activity::factory()->create([
            'created_by' => $host->id,
            'activity_series_id' => $draftSeries->id,
            'hosting_mode' => Activity::HOSTING_MODE_DRAFT,
        ]);

        $stats = app(CatalogSeriesSortStats::class)->all();

        $this->assertSame(2, $stats['activity'][(int) $visibleSeries->id]['activities']);
        $this->assertSame(2, $stats['activity'][(int) $visibleSeries->id]['participants']);
        $this->assertArrayNotHasKey((int) $draftSeries->id, $stats['activity']);
    }

    #[Test]
    public function it_counts_event_series_activities_and_participants_from_public_programme(): void
    {
        Cache::forget(CatalogSeriesSortStats::CACHE_KEY);

        $owner = User::factory()->create();
        $host = User::factory()->create();
        $guest = User::factory()->create();
        $series = EventSeries::factory()->create(['created_by' => $owner->id]);
        $event = Event::factory()->public()->create([
            'created_by' => $owner->id,
            'event_series_id' => $series->id,
            'starts_at' => now()->addDays(5),
            'ends_at' => now()->addDays(5)->addHours(4),
        ]);

        $programme = Activity::factory()->scheduled()->create([
            'created_by' => $host->id,
            'updated_by' => $host->id,
        ]);
        Slot::factory()->create([
            'event_id' => $event->id,
            'activity_id' => $programme->id,
        ]);
        ActivityUser::query()->create([
            'activity_id' => $programme->id,
            'user_id' => $guest->id,
            'is_absent' => false,
        ]);

        $privateSeries = EventSeries::factory()->create(['created_by' => $owner->id]);
        $privateEvent = Event::factory()->private()->create([
            'created_by' => $owner->id,
            'event_series_id' => $privateSeries->id,
        ]);
        $privateActivity = Activity::factory()->scheduled()->create([
            'created_by' => $host->id,
        ]);
        Slot::factory()->create([
            'event_id' => $privateEvent->id,
            'activity_id' => $privateActivity->id,
        ]);

        $stats = app(CatalogSeriesSortStats::class)->all();

        $this->assertSame(1, $stats['event'][(int) $series->id]['activities']);
        $this->assertSame(2, $stats['event'][(int) $series->id]['participants']);
        $this->assertArrayNotHasKey((int) $privateSeries->id, $stats['event']);
    }
}
