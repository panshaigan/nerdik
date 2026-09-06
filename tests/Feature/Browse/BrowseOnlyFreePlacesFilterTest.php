<?php

declare(strict_types=1);

namespace Tests\Feature\Browse;

use App\Livewire\Browse\BrowseEvents;
use App\Models\Activity;
use App\Models\ActivityUser;
use App\Models\Event;
use App\Models\Place;
use App\Models\Slot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class BrowseOnlyFreePlacesFilterTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{startsAt: Carbon, endsAt: Carbon, place: Place, owner: User, event: Event}
     */
    private function upcomingScheduledContext(): array
    {
        $owner = User::factory()->create();
        $startsAt = now()->addDays(14)->setSecond(0);
        $endsAt = (clone $startsAt)->addHours(5);
        $place = Place::factory()->venue()->create([
            'latitude' => 51.11,
            'longitude' => 17.03,
        ]);
        $event = Event::factory()->public()->create([
            'created_by' => $owner->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);
        $event->places()->attach($place->id);

        return compact('startsAt', 'endsAt', 'place', 'owner', 'event');
    }

    private function scheduledActivity(
        User $owner,
        Event $event,
        Carbon $startsAt,
        Carbon $endsAt,
        string $name,
        ?int $maxParticipants = null,
    ): Activity {
        $activity = Activity::factory()->scheduled()->create([
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
            'name' => $name,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'max_participants' => $maxParticipants,
        ]);
        Slot::factory()->create([
            'event_id' => $event->id,
            'activity_id' => $activity->id,
            'place_id' => $event->places()->first()?->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);

        return $activity;
    }

    /**
     * @return list<string>
     */
    private function browseActivityNames(Testable $component): array
    {
        /** @var LengthAwarePaginator<int, array{kind: string, event?: Event, activity?: Activity}> $paginator */
        $paginator = $component->viewData('browseListings');

        return $paginator->getCollection()
            ->filter(fn (array $row): bool => $row['kind'] === 'activity')
            ->map(fn (array $row): string => (string) $row['activity']->name)
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function browseEventNames(Testable $component): array
    {
        /** @var LengthAwarePaginator<int, array{kind: string, event?: Event, activity?: Activity}> $paginator */
        $paginator = $component->viewData('browseListings');

        return $paginator->getCollection()
            ->filter(fn (array $row): bool => $row['kind'] === 'event')
            ->map(fn (array $row): string => (string) $row['event']->name)
            ->values()
            ->all();
    }

    public function test_full_activity_is_hidden_when_filter_is_enabled(): void
    {
        $ctx = $this->upcomingScheduledContext();
        $fullActivity = $this->scheduledActivity(
            $ctx['owner'],
            $ctx['event'],
            $ctx['startsAt'],
            $ctx['endsAt'],
            'Free Places Full Activity',
            maxParticipants: 2,
        );

        foreach (User::factory()->count(2)->create() as $participant) {
            ActivityUser::query()->create([
                'activity_id' => $fullActivity->id,
                'user_id' => $participant->id,
                'is_absent' => false,
            ]);
        }

        Livewire::withoutLazyLoading()
            ->test(BrowseEvents::class)
            ->set('only_free_places', true)
            ->set('only_activities', true)
            ->assertDontSee('Free Places Full Activity');
    }

    public function test_activity_with_open_spots_is_visible_when_filter_is_enabled(): void
    {
        $ctx = $this->upcomingScheduledContext();
        $openActivity = $this->scheduledActivity(
            $ctx['owner'],
            $ctx['event'],
            $ctx['startsAt'],
            $ctx['endsAt'],
            'Free Places Open Activity',
            maxParticipants: 4,
        );

        ActivityUser::query()->create([
            'activity_id' => $openActivity->id,
            'user_id' => User::factory()->create()->id,
            'is_absent' => false,
        ]);

        Livewire::withoutLazyLoading()
            ->test(BrowseEvents::class)
            ->set('only_free_places', true)
            ->set('only_activities', true)
            ->assertSee('Free Places Open Activity');
    }

    public function test_activity_without_participant_cap_is_visible_when_filter_is_enabled(): void
    {
        $ctx = $this->upcomingScheduledContext();
        $this->scheduledActivity(
            $ctx['owner'],
            $ctx['event'],
            $ctx['startsAt'],
            $ctx['endsAt'],
            'Free Places Unlimited Activity',
            maxParticipants: null,
        );

        Livewire::withoutLazyLoading()
            ->test(BrowseEvents::class)
            ->set('only_free_places', true)
            ->set('only_activities', true)
            ->assertSee('Free Places Unlimited Activity');
    }

    public function test_mixed_mode_still_shows_events_when_filter_is_enabled(): void
    {
        $ctx = $this->upcomingScheduledContext();
        $ctx['event']->update(['name' => 'Free Places Mixed Event']);
        $fullActivity = $this->scheduledActivity(
            $ctx['owner'],
            $ctx['event'],
            $ctx['startsAt'],
            $ctx['endsAt'],
            'Free Places Mixed Full Activity',
            maxParticipants: 1,
        );

        ActivityUser::query()->create([
            'activity_id' => $fullActivity->id,
            'user_id' => User::factory()->create()->id,
            'is_absent' => false,
        ]);

        $component = Livewire::withoutLazyLoading()
            ->test(BrowseEvents::class)
            ->set('only_free_places', true);

        $this->assertContains('Free Places Mixed Event', $this->browseEventNames($component));
        $this->assertNotContains('Free Places Mixed Full Activity', $this->browseActivityNames($component));
    }

    public function test_map_features_excludes_full_activities_when_filter_is_enabled(): void
    {
        $ctx = $this->upcomingScheduledContext();
        $ctx['event']->update(['name' => 'Map Free Places Hosting Event']);

        $fullActivity = $this->scheduledActivity(
            $ctx['owner'],
            $ctx['event'],
            $ctx['startsAt'],
            $ctx['endsAt'],
            'Map Free Places Full Activity',
            maxParticipants: 1,
        );
        $this->scheduledActivity(
            $ctx['owner'],
            $ctx['event'],
            $ctx['startsAt'],
            $ctx['endsAt'],
            'Map Free Places Open Activity',
            maxParticipants: 3,
        );

        ActivityUser::query()->create([
            'activity_id' => $fullActivity->id,
            'user_id' => User::factory()->create()->id,
            'is_absent' => false,
        ]);

        $res = $this->getJson(route('search.map-features', [
            'min_lat' => 51.0,
            'max_lat' => 51.2,
            'min_lng' => 16.9,
            'max_lng' => 17.2,
            'zoom' => 12,
            'only_free_places' => true,
            'only_activities' => true,
        ]));

        $res->assertOk();
        $features = collect($res->json('features'));
        $names = $features
            ->pluck('properties.name')
            ->filter()
            ->values()
            ->all();

        // Scheduled activities resolve to the hosting event on the map.
        $this->assertContains('Map Free Places Hosting Event', $names);
        $this->assertNotContains('Map Free Places Open Activity', $names);
        $this->assertNotContains('Map Free Places Full Activity', $names);

        $eventFeature = $features->first(
            fn (array $f): bool => ($f['properties']['kind'] ?? null) === 'event'
                && (int) ($f['properties']['id'] ?? 0) === (int) $ctx['event']->id
        );
        $this->assertNotNull($eventFeature);
        $this->assertSame(2, (int) ($eventFeature['properties']['activity_count'] ?? 0));
    }
}
