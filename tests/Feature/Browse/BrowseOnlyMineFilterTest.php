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

class BrowseOnlyMineFilterTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{startsAt: Carbon, endsAt: Carbon, place: Place}
     */
    private function upcomingVenue(): array
    {
        $startsAt = now()->addDays(14)->setSecond(0);
        $endsAt = (clone $startsAt)->addHours(5);
        $place = Place::factory()->venue()->create([
            'latitude' => 51.11,
            'longitude' => 17.03,
        ]);

        return compact('startsAt', 'endsAt', 'place');
    }

    public function test_guest_only_mine_url_param_is_ignored(): void
    {
        $owner = User::factory()->create();
        $venue = $this->upcomingVenue();

        $publicEvent = Event::factory()->public()->create([
            'created_by' => $owner->id,
            'name' => 'Guest Browse Public Event Visible',
            'starts_at' => $venue['startsAt'],
            'ends_at' => $venue['endsAt'],
        ]);
        $publicEvent->places()->attach($venue['place']->id);

        Livewire::withoutLazyLoading()
            ->test(BrowseEvents::class)
            ->set('only_mine', true)
            ->assertSee('Guest Browse Public Event Visible');
    }

    public function test_creator_sees_own_private_event_with_only_mine(): void
    {
        $creator = User::factory()->create();
        $other = User::factory()->create();
        $venue = $this->upcomingVenue();

        $privateEvent = Event::factory()->private()->create([
            'created_by' => $creator->id,
            'name' => 'Only Mine Private Event Creator',
            'starts_at' => $venue['startsAt'],
            'ends_at' => $venue['endsAt'],
        ]);
        $privateEvent->places()->attach($venue['place']->id);

        $publicOther = Event::factory()->public()->create([
            'created_by' => $other->id,
            'name' => 'Only Mine Public Event Other',
            'starts_at' => $venue['startsAt'],
            'ends_at' => $venue['endsAt'],
        ]);
        $publicOther->places()->attach($venue['place']->id);

        $creatorComponent = Livewire::withoutLazyLoading()
            ->actingAs($creator)
            ->test(BrowseEvents::class)
            ->set('only_mine', true)
            ->set('only_events', true);

        $creatorEventNames = $this->browseEventNames($creatorComponent);
        $this->assertContains('Only Mine Private Event Creator', $creatorEventNames);
        $this->assertNotContains('Only Mine Public Event Other', $creatorEventNames);

        $otherComponent = Livewire::withoutLazyLoading()
            ->actingAs($other)
            ->test(BrowseEvents::class)
            ->set('only_mine', true)
            ->set('only_events', true);

        $otherEventNames = $this->browseEventNames($otherComponent);
        $this->assertNotContains('Only Mine Private Event Creator', $otherEventNames);
        $this->assertContains('Only Mine Public Event Other', $otherEventNames);
    }

    public function test_participant_sees_public_event_they_joined(): void
    {
        $host = User::factory()->create();
        $participant = User::factory()->create();
        $venue = $this->upcomingVenue();

        $joinedEvent = Event::factory()->public()->create([
            'created_by' => $host->id,
            'name' => 'Only Mine Joined Event',
            'starts_at' => $venue['startsAt'],
            'ends_at' => $venue['endsAt'],
        ]);
        $joinedEvent->places()->attach($venue['place']->id);

        $otherEvent = Event::factory()->public()->create([
            'created_by' => $host->id,
            'name' => 'Only Mine Unrelated Event',
            'starts_at' => $venue['startsAt'],
            'ends_at' => $venue['endsAt'],
        ]);
        $otherEvent->places()->attach($venue['place']->id);

        $activity = Activity::factory()->scheduled()->create([
            'created_by' => $host->id,
            'updated_by' => $host->id,
            'name' => 'Only Mine Joined Activity',
            'starts_at' => $venue['startsAt'],
            'ends_at' => $venue['endsAt'],
        ]);
        Slot::factory()->create([
            'event_id' => $joinedEvent->id,
            'activity_id' => $activity->id,
            'starts_at' => $venue['startsAt'],
            'ends_at' => $venue['endsAt'],
        ]);

        ActivityUser::query()->create([
            'activity_id' => $activity->id,
            'user_id' => $participant->id,
            'is_absent' => false,
        ]);

        $participantComponent = Livewire::withoutLazyLoading()
            ->actingAs($participant)
            ->test(BrowseEvents::class)
            ->set('only_mine', true)
            ->set('only_events', true);

        $eventNames = $this->browseEventNames($participantComponent);
        $this->assertContains('Only Mine Joined Event', $eventNames);
        $this->assertNotContains('Only Mine Unrelated Event', $eventNames);
    }

    public function test_activity_only_mine_shows_created_and_joined(): void
    {
        $creator = User::factory()->create();
        $participant = User::factory()->create();
        $venue = $this->upcomingVenue();

        $ownActivity = Activity::factory()->create([
            'created_by' => $creator->id,
            'updated_by' => $creator->id,
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'place_id' => $venue['place']->id,
            'name' => 'Only Mine Own Self Hosted',
            'starts_at' => $venue['startsAt'],
            'ends_at' => $venue['endsAt'],
        ]);

        $event = Event::factory()->public()->create([
            'created_by' => $creator->id,
            'starts_at' => $venue['startsAt'],
            'ends_at' => $venue['endsAt'],
        ]);

        $joinedActivity = Activity::factory()->scheduled()->create([
            'created_by' => $creator->id,
            'updated_by' => $creator->id,
            'name' => 'Only Mine Joined Scheduled Activity',
            'starts_at' => $venue['startsAt'],
            'ends_at' => $venue['endsAt'],
        ]);
        Slot::factory()->create([
            'event_id' => $event->id,
            'activity_id' => $joinedActivity->id,
            'starts_at' => $venue['startsAt'],
            'ends_at' => $venue['endsAt'],
        ]);

        ActivityUser::query()->create([
            'activity_id' => $joinedActivity->id,
            'user_id' => $participant->id,
            'is_absent' => false,
        ]);

        $stranger = User::factory()->create();
        $strangerActivity = Activity::factory()->create([
            'created_by' => $stranger->id,
            'updated_by' => $stranger->id,
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'place_id' => $venue['place']->id,
            'name' => 'Only Mine Stranger Self Hosted',
            'starts_at' => $venue['startsAt'],
            'ends_at' => $venue['endsAt'],
        ]);

        $creatorComponent = Livewire::withoutLazyLoading()
            ->actingAs($creator)
            ->test(BrowseEvents::class)
            ->set('only_mine', true)
            ->set('only_activities', true);

        $creatorActivityNames = $this->browseActivityNames($creatorComponent);
        $this->assertContains('Only Mine Own Self Hosted', $creatorActivityNames);
        $this->assertContains('Only Mine Joined Scheduled Activity', $creatorActivityNames);
        $this->assertNotContains('Only Mine Stranger Self Hosted', $creatorActivityNames);

        $participantComponent = Livewire::withoutLazyLoading()
            ->actingAs($participant)
            ->test(BrowseEvents::class)
            ->set('only_mine', true)
            ->set('only_activities', true);

        $participantActivityNames = $this->browseActivityNames($participantComponent);
        $this->assertContains('Only Mine Joined Scheduled Activity', $participantActivityNames);
        $this->assertNotContains('Only Mine Own Self Hosted', $participantActivityNames);
        $this->assertNotContains($strangerActivity->name, $participantActivityNames);
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

    public function test_map_features_respects_only_mine_for_authenticated_user(): void
    {
        $creator = User::factory()->create();
        $other = User::factory()->create();
        $venue = $this->upcomingVenue();

        $mine = Event::factory()->public()->create([
            'created_by' => $creator->id,
            'name' => 'Map Only Mine Event',
            'starts_at' => $venue['startsAt'],
            'ends_at' => $venue['endsAt'],
        ]);
        $mine->places()->attach($venue['place']->id);

        $notMine = Event::factory()->public()->create([
            'created_by' => $other->id,
            'name' => 'Map Not Mine Event',
            'starts_at' => $venue['startsAt'],
            'ends_at' => $venue['endsAt'],
        ]);
        $notMine->places()->attach($venue['place']->id);

        $bbox = [
            'min_lat' => 51.0,
            'max_lat' => 51.2,
            'min_lng' => 16.9,
            'max_lng' => 17.2,
            'zoom' => 12,
            'only_mine' => true,
            'only_events' => true,
        ];

        $res = $this->actingAs($creator)->getJson(route('search.map-features', $bbox));
        $res->assertOk();
        $names = array_map(
            static fn (array $f): string => (string) ($f['properties']['name'] ?? ''),
            $res->json('features') ?? []
        );
        $this->assertContains('Map Only Mine Event', $names);
        $this->assertNotContains('Map Not Mine Event', $names);
    }
}
