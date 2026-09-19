<?php

declare(strict_types=1);

namespace Tests\Feature\Browse;

use App\Livewire\Browse\BrowseEvents;
use App\Models\Activity;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Place;
use App\Models\Slot;
use App\Models\User;
use App\Support\Browse\BrowseListingFilterBag;
use App\Support\Browse\BrowseSearchUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

final class BrowsePlaceAndOrganizationFilterTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{startsAt: Carbon, endsAt: Carbon, owner: User}
     */
    private function upcomingOwner(): array
    {
        $owner = User::factory()->create();
        $startsAt = now()->addDays(14)->setSecond(0);
        $endsAt = (clone $startsAt)->addHours(5);

        return compact('startsAt', 'endsAt', 'owner');
    }

    private function publicEvent(
        User $owner,
        Place $place,
        Carbon $startsAt,
        Carbon $endsAt,
        string $name,
        ?Organization $organization = null,
    ): Event {
        $event = Event::factory()->public()->create([
            'created_by' => $owner->id,
            'name' => $name,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'organization_id' => $organization?->id,
        ]);
        $event->places()->attach($place->id);

        return $event;
    }

    private function scheduledActivity(
        User $owner,
        Event $event,
        Place $place,
        Carbon $startsAt,
        Carbon $endsAt,
        string $name,
    ): Activity {
        $activity = Activity::factory()->scheduled()->create([
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
            'name' => $name,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);
        Slot::factory()->create([
            'event_id' => $event->id,
            'activity_id' => $activity->id,
            'place_id' => $place->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);

        return $activity;
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

    public function test_place_filter_keeps_venue_event_and_room_activity(): void
    {
        $ctx = $this->upcomingOwner();
        $venue = Place::factory()->venue()->create(['name' => 'Filter Venue Alpha']);
        $room = Place::factory()->room($venue)->create(['name' => 'Filter Room Alpha']);
        $otherVenue = Place::factory()->venue()->create(['name' => 'Filter Venue Beta']);

        $this->publicEvent($ctx['owner'], $venue, $ctx['startsAt'], $ctx['endsAt'], 'Place Filter Event Alpha');
        $eventWithRoom = $this->publicEvent($ctx['owner'], $venue, $ctx['startsAt'], $ctx['endsAt'], 'Place Filter Event With Room Activity');
        $this->scheduledActivity(
            $ctx['owner'],
            $eventWithRoom,
            $room,
            $ctx['startsAt'],
            $ctx['endsAt'],
            'Place Filter Activity In Room',
        );
        $this->publicEvent($ctx['owner'], $otherVenue, $ctx['startsAt'], $ctx['endsAt'], 'Place Filter Event Other');

        $component = Livewire::withoutLazyLoading()
            ->test(BrowseEvents::class)
            ->set('place_id', $venue->id);

        $names = $this->browseListingNames($component);

        $this->assertContains('Place Filter Event Alpha', $names);
        $this->assertContains('Place Filter Event With Room Activity', $names);
        $this->assertContains('Place Filter Activity In Room', $names);
        $this->assertNotContains('Place Filter Event Other', $names);
        $component->assertSeeHtml(BrowseSearchUrl::forPlace($venue));
    }

    public function test_room_place_id_resolves_to_venue_and_matches_listings(): void
    {
        $ctx = $this->upcomingOwner();
        $venue = Place::factory()->venue()->create(['name' => 'Resolve Venue']);
        $room = Place::factory()->room($venue)->create(['name' => 'Resolve Room']);
        $this->publicEvent($ctx['owner'], $venue, $ctx['startsAt'], $ctx['endsAt'], 'Resolve Venue Event');

        $component = Livewire::withoutLazyLoading()
            ->test(BrowseEvents::class, ['place_id' => $room->id]);

        $this->assertSame($venue->id, $component->get('place_id'));
        $this->assertContains('Resolve Venue Event', $this->browseListingNames($component));
    }

    public function test_organization_filter_keeps_org_event_and_scheduled_activity(): void
    {
        $ctx = $this->upcomingOwner();
        $org = Organization::factory()->create(['name' => 'Filter Org Alpha']);
        $otherOrg = Organization::factory()->create(['name' => 'Filter Org Beta']);
        $venue = Place::factory()->venue()->create();

        $orgEvent = $this->publicEvent(
            $ctx['owner'],
            $venue,
            $ctx['startsAt'],
            $ctx['endsAt'],
            'Org Filter Event Alpha',
            $org,
        );
        $this->scheduledActivity(
            $ctx['owner'],
            $orgEvent,
            $venue,
            $ctx['startsAt'],
            $ctx['endsAt'],
            'Org Filter Scheduled Activity',
        );
        $this->publicEvent(
            $ctx['owner'],
            $venue,
            $ctx['startsAt'],
            $ctx['endsAt'],
            'Org Filter Event Other',
            $otherOrg,
        );

        Activity::factory()->create([
            'created_by' => $ctx['owner']->id,
            'updated_by' => $ctx['owner']->id,
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'place_id' => $venue->id,
            'starts_at' => $ctx['startsAt'],
            'ends_at' => $ctx['endsAt'],
            'name' => 'Org Filter Self Hosted',
        ]);

        $component = Livewire::withoutLazyLoading()
            ->test(BrowseEvents::class)
            ->set('organization_id', $org->id);

        $names = $this->browseListingNames($component);

        $this->assertContains('Org Filter Event Alpha', $names);
        $this->assertContains('Org Filter Scheduled Activity', $names);
        $this->assertNotContains('Org Filter Event Other', $names);
        $this->assertNotContains('Org Filter Self Hosted', $names);
    }

    public function test_filter_bag_from_request_parses_place_and_organization_ids(): void
    {
        $venue = Place::factory()->venue()->create();
        $organization = Organization::factory()->create();
        $request = Request::create(
            '/search?place_id='.$venue->id.'&organization_id='.$organization->id
        );
        $bag = BrowseListingFilterBag::fromRequest($request);

        $this->assertSame($venue->id, $bag->placeId);
        $this->assertSame($organization->id, $bag->organizationId);
    }

    public function test_clear_filters_drops_place_and_organization(): void
    {
        Livewire::withoutLazyLoading()
            ->test(BrowseEvents::class, [
                'place_id' => 3,
                'organization_id' => 9,
            ])
            ->call('clearFilters')
            ->assertRedirect(route('search.index'));
    }
}
