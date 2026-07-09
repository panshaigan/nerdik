<?php

declare(strict_types=1);

namespace Tests\Feature\Browse;

use App\Livewire\Browse\BrowseEvents;
use App\Models\Activity;
use App\Models\Event;
use App\Models\Place;
use App\Models\Slot;
use App\Models\User;
use App\Support\Browse\BrowseSearchUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class BrowseDateRangeFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-03-01 12:00:00');
    }

    /**
     * @return array{owner: User, place: Place}
     */
    private function venueContext(): array
    {
        $owner = User::factory()->create();
        $place = Place::factory()->venue()->create([
            'latitude' => 51.11,
            'longitude' => 17.03,
        ]);

        return compact('owner', 'place');
    }

    private function publicEvent(
        User $owner,
        Place $place,
        Carbon $startsAt,
        Carbon $endsAt,
        string $name,
    ): Event {
        $event = Event::factory()->public()->create([
            'created_by' => $owner->id,
            'name' => $name,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);
        $event->places()->attach($place->id);

        return $event;
    }

    /**
     * @return list<string>
     */
    private function browseListingNames(Testable $component): array
    {
        /** @var LengthAwarePaginator<int, array{kind: string, event?: Event, activity?: Activity}> $paginator */
        $paginator = $component->viewData('browseListings');

        return $paginator->getCollection()
            ->map(function (array $row): string {
                if ($row['kind'] === 'event') {
                    return (string) $row['event']->name;
                }

                return (string) $row['activity']->name;
            })
            ->values()
            ->all();
    }

    public function test_event_overlap_includes_spanning_and_inside_range_and_excludes_outside(): void
    {
        ['owner' => $owner, 'place' => $place] = $this->venueContext();

        $this->publicEvent(
            $owner,
            $place,
            Carbon::parse('2026-03-05 10:00:00'),
            Carbon::parse('2026-03-25 18:00:00'),
            'Date Range Event Spanning',
        );
        $this->publicEvent(
            $owner,
            $place,
            Carbon::parse('2026-03-12 10:00:00'),
            Carbon::parse('2026-03-15 18:00:00'),
            'Date Range Event Inside',
        );
        $this->publicEvent(
            $owner,
            $place,
            Carbon::parse('2026-02-20 10:00:00'),
            Carbon::parse('2026-03-05 18:00:00'),
            'Date Range Event Before',
        );
        $this->publicEvent(
            $owner,
            $place,
            Carbon::parse('2026-03-25 10:00:00'),
            Carbon::parse('2026-03-30 18:00:00'),
            'Date Range Event After',
        );

        $component = Livewire::withoutLazyLoading()
            ->test(BrowseEvents::class)
            ->set('only_events', true)
            ->set('from_date', '2026-03-10')
            ->set('to_date', '2026-03-20');

        $names = $this->browseListingNames($component);

        $this->assertContains('Date Range Event Spanning', $names);
        $this->assertContains('Date Range Event Inside', $names);
        $this->assertNotContains('Date Range Event Before', $names);
        $this->assertNotContains('Date Range Event After', $names);
    }

    public function test_self_hosted_activity_overlap_respects_range(): void
    {
        ['owner' => $owner, 'place' => $place] = $this->venueContext();

        Activity::factory()->create([
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'place_id' => $place->id,
            'name' => 'Date Range Self Hosted Inside',
            'starts_at' => Carbon::parse('2026-03-12 10:00:00'),
            'ends_at' => Carbon::parse('2026-03-14 18:00:00'),
        ]);
        Activity::factory()->create([
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'place_id' => $place->id,
            'name' => 'Date Range Self Hosted After',
            'starts_at' => Carbon::parse('2026-03-25 10:00:00'),
            'ends_at' => Carbon::parse('2026-03-26 18:00:00'),
        ]);

        $names = $this->browseListingNames(
            Livewire::withoutLazyLoading()
                ->test(BrowseEvents::class)
                ->set('only_activities', true)
                ->set('from_date', '2026-03-10')
                ->set('to_date', '2026-03-20')
        );

        $this->assertContains('Date Range Self Hosted Inside', $names);
        $this->assertNotContains('Date Range Self Hosted After', $names);
    }

    public function test_scheduled_activity_overlap_uses_slot_schedule(): void
    {
        ['owner' => $owner, 'place' => $place] = $this->venueContext();
        $event = $this->publicEvent(
            $owner,
            $place,
            Carbon::parse('2026-03-01 10:00:00'),
            Carbon::parse('2026-04-01 18:00:00'),
            'Date Range Parent Event',
        );

        $inside = Activity::factory()->scheduled()->create([
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
            'name' => 'Date Range Scheduled Inside',
            'starts_at' => Carbon::parse('2026-03-12 10:00:00'),
            'ends_at' => Carbon::parse('2026-03-14 18:00:00'),
        ]);
        Slot::factory()->create([
            'event_id' => $event->id,
            'activity_id' => $inside->id,
            'place_id' => $place->id,
            'starts_at' => Carbon::parse('2026-03-12 10:00:00'),
            'ends_at' => Carbon::parse('2026-03-14 18:00:00'),
        ]);

        $after = Activity::factory()->scheduled()->create([
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
            'name' => 'Date Range Scheduled After',
            'starts_at' => Carbon::parse('2026-03-25 10:00:00'),
            'ends_at' => Carbon::parse('2026-03-26 18:00:00'),
        ]);
        Slot::factory()->create([
            'event_id' => $event->id,
            'activity_id' => $after->id,
            'place_id' => $place->id,
            'starts_at' => Carbon::parse('2026-03-25 10:00:00'),
            'ends_at' => Carbon::parse('2026-03-26 18:00:00'),
        ]);

        $names = $this->browseListingNames(
            Livewire::withoutLazyLoading()
                ->test(BrowseEvents::class)
                ->set('only_activities', true)
                ->set('from_date', '2026-03-10')
                ->set('to_date', '2026-03-20')
        );

        $this->assertContains('Date Range Scheduled Inside', $names);
        $this->assertNotContains('Date Range Scheduled After', $names);
    }

    public function test_from_date_only_requires_schedule_end_on_or_after_from(): void
    {
        ['owner' => $owner, 'place' => $place] = $this->venueContext();

        $this->publicEvent(
            $owner,
            $place,
            Carbon::parse('2026-03-05 10:00:00'),
            Carbon::parse('2026-03-12 18:00:00'),
            'Date Range From Only Overlap',
        );
        $this->publicEvent(
            $owner,
            $place,
            Carbon::parse('2026-02-20 10:00:00'),
            Carbon::parse('2026-03-05 18:00:00'),
            'Date Range From Only Before',
        );

        $names = $this->browseListingNames(
            Livewire::withoutLazyLoading()
                ->test(BrowseEvents::class)
                ->set('only_events', true)
                ->set('from_date', '2026-03-10')
        );

        $this->assertContains('Date Range From Only Overlap', $names);
        $this->assertNotContains('Date Range From Only Before', $names);
    }

    public function test_to_date_only_requires_schedule_start_on_or_before_to(): void
    {
        ['owner' => $owner, 'place' => $place] = $this->venueContext();

        $this->publicEvent(
            $owner,
            $place,
            Carbon::parse('2026-03-12 10:00:00'),
            Carbon::parse('2026-03-18 18:00:00'),
            'Date Range To Only Overlap',
        );
        $this->publicEvent(
            $owner,
            $place,
            Carbon::parse('2026-03-25 10:00:00'),
            Carbon::parse('2026-03-30 18:00:00'),
            'Date Range To Only After',
        );

        $names = $this->browseListingNames(
            Livewire::withoutLazyLoading()
                ->test(BrowseEvents::class)
                ->set('only_events', true)
                ->set('to_date', '2026-03-20')
        );

        $this->assertContains('Date Range To Only Overlap', $names);
        $this->assertNotContains('Date Range To Only After', $names);
    }

    public function test_clear_filters_resets_date_range(): void
    {
        Livewire::withoutLazyLoading()
            ->test(BrowseEvents::class)
            ->set('from_date', '2026-03-10')
            ->set('to_date', '2026-03-20')
            ->call('clearFilters')
            ->assertSet('from_date', null)
            ->assertSet('to_date', null);
    }

    public function test_return_url_includes_date_range(): void
    {
        $component = Livewire::withoutLazyLoading()
            ->test(BrowseEvents::class)
            ->set('from_date', '2026-03-10')
            ->set('to_date', '2026-03-20');

        $url = BrowseSearchUrl::returnUrlFromFilterBag($component->instance()->browseFilterBag());

        $this->assertStringContainsString('from_date=2026-03-10', $url);
        $this->assertStringContainsString('to_date=2026-03-20', $url);
    }

    public function test_map_features_respects_date_range(): void
    {
        ['owner' => $owner, 'place' => $place] = $this->venueContext();

        $this->publicEvent(
            $owner,
            $place,
            Carbon::parse('2026-03-12 10:00:00'),
            Carbon::parse('2026-03-14 18:00:00'),
            'Date Range Map Inside',
        );
        $this->publicEvent(
            $owner,
            $place,
            Carbon::parse('2026-04-10 10:00:00'),
            Carbon::parse('2026-04-12 18:00:00'),
            'Date Range Map Outside',
        );

        $res = $this->getJson(route('search.map-features', [
            'min_lat' => 51.0,
            'max_lat' => 51.2,
            'min_lng' => 16.9,
            'max_lng' => 17.2,
            'zoom' => 12,
            'from_date' => '2026-03-10',
            'to_date' => '2026-03-20',
        ]));

        $res->assertOk();
        $names = array_values(array_filter(array_map(
            static fn (array $feature): ?string => isset($feature['properties']['name'])
                ? (string) $feature['properties']['name']
                : null,
            $res->json('features') ?? []
        )));

        $this->assertContains('Date Range Map Inside', $names);
        $this->assertNotContains('Date Range Map Outside', $names);
    }
}
