<?php

declare(strict_types=1);

namespace Tests\Feature\Browse;

use App\Livewire\Browse\BrowseEvents;
use App\Models\Activity;
use App\Models\ActivityType;
use App\Models\Event;
use App\Models\Place;
use App\Models\Slot;
use App\Models\User;
use Database\Seeders\ActivityTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

final class BrowseActivityTypeFilterTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{startsAt: Carbon, endsAt: Carbon, owner: User, venue: Place, board: ActivityType, rpg: ActivityType}
     */
    private function context(): array
    {
        $this->seed(ActivityTypeSeeder::class);

        $owner = User::factory()->create();
        $startsAt = now()->addDays(14)->setSecond(0);
        $endsAt = (clone $startsAt)->addHours(5);
        $venue = Place::factory()->venue()->create();
        $board = ActivityType::findBySlug(ActivityType::SLUG_BOARD);
        $rpg = ActivityType::findBySlug(ActivityType::SLUG_RPG);
        $this->assertNotNull($board);
        $this->assertNotNull($rpg);

        return compact('startsAt', 'endsAt', 'owner', 'venue', 'board', 'rpg');
    }

    private function publicEvent(User $owner, Place $venue, Carbon $startsAt, Carbon $endsAt, string $name): Event
    {
        $event = Event::factory()->public()->create([
            'created_by' => $owner->id,
            'name' => $name,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);
        $event->places()->attach($venue->id);

        return $event;
    }

    private function scheduledActivity(
        User $owner,
        Event $event,
        Place $venue,
        Carbon $startsAt,
        Carbon $endsAt,
        string $name,
        ActivityType $type,
    ): Activity {
        $activity = Activity::factory()->scheduled()->create([
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
            'name' => $name,
            'activity_type_id' => $type->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);
        Slot::factory()->create([
            'event_id' => $event->id,
            'activity_id' => $activity->id,
            'place_id' => $venue->id,
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

    public function test_activity_type_filter_keeps_matching_activities_and_hosting_events(): void
    {
        $ctx = $this->context();

        $boardEvent = $this->publicEvent(
            $ctx['owner'],
            $ctx['venue'],
            $ctx['startsAt'],
            $ctx['endsAt'],
            'Type Filter Board Event',
        );
        $this->scheduledActivity(
            $ctx['owner'],
            $boardEvent,
            $ctx['venue'],
            $ctx['startsAt'],
            $ctx['endsAt'],
            'Type Filter Board Activity',
            $ctx['board'],
        );

        $rpgEvent = $this->publicEvent(
            $ctx['owner'],
            $ctx['venue'],
            $ctx['startsAt'],
            $ctx['endsAt'],
            'Type Filter Rpg Event',
        );
        $this->scheduledActivity(
            $ctx['owner'],
            $rpgEvent,
            $ctx['venue'],
            $ctx['startsAt'],
            $ctx['endsAt'],
            'Type Filter Rpg Activity',
            $ctx['rpg'],
        );

        Activity::factory()->create([
            'created_by' => $ctx['owner']->id,
            'updated_by' => $ctx['owner']->id,
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'place_id' => $ctx['venue']->id,
            'activity_type_id' => $ctx['board']->id,
            'starts_at' => $ctx['startsAt'],
            'ends_at' => $ctx['endsAt'],
            'name' => 'Type Filter Self Hosted Board',
        ]);

        $openSlotEvent = $this->publicEvent(
            $ctx['owner'],
            $ctx['venue'],
            $ctx['startsAt'],
            $ctx['endsAt'],
            'Type Filter Open Board Slot Event',
        );
        $openSlot = Slot::factory()->create([
            'event_id' => $openSlotEvent->id,
            'activity_id' => null,
            'place_id' => $ctx['venue']->id,
            'starts_at' => $ctx['startsAt'],
            'ends_at' => $ctx['endsAt'],
        ]);
        $openSlot->setActivityTypes([$ctx['board']->id]);

        $unrestrictedEvent = $this->publicEvent(
            $ctx['owner'],
            $ctx['venue'],
            $ctx['startsAt'],
            $ctx['endsAt'],
            'Type Filter Unrestricted Event',
        );
        Slot::factory()->create([
            'event_id' => $unrestrictedEvent->id,
            'activity_id' => null,
            'place_id' => $ctx['venue']->id,
            'starts_at' => $ctx['startsAt'],
            'ends_at' => $ctx['endsAt'],
        ]);

        $cancelledEvent = $this->publicEvent(
            $ctx['owner'],
            $ctx['venue'],
            $ctx['startsAt'],
            $ctx['endsAt'],
            'Type Filter Cancelled Board Event',
        );
        $this->scheduledActivity(
            $ctx['owner'],
            $cancelledEvent,
            $ctx['venue'],
            $ctx['startsAt'],
            $ctx['endsAt'],
            'Type Filter Cancelled Board Activity',
            $ctx['board'],
        );
        Activity::query()->where('name', 'Type Filter Cancelled Board Activity')->update([
            'cancelled_at' => now(),
        ]);

        $names = $this->browseListingNames(
            Livewire::withoutLazyLoading()
                ->test(BrowseEvents::class)
                ->set('activity_type_id', $ctx['board']->id)
        );

        $this->assertContains('Type Filter Board Event', $names);
        $this->assertContains('Type Filter Board Activity', $names);
        $this->assertContains('Type Filter Self Hosted Board', $names);
        $this->assertContains('Type Filter Open Board Slot Event', $names);
        $this->assertNotContains('Type Filter Rpg Event', $names);
        $this->assertNotContains('Type Filter Rpg Activity', $names);
        $this->assertNotContains('Type Filter Unrestricted Event', $names);
        $this->assertNotContains('Type Filter Cancelled Board Event', $names);
        $this->assertNotContains('Type Filter Cancelled Board Activity', $names);
    }
}
