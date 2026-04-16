<?php

namespace Database\Seeders;

use App\Models\Activity;
use App\Models\ActivityProposal;
use App\Models\ActivityType;
use App\Models\City;
use App\Models\Event;
use App\Models\EventEnrollmentWindow;
use App\Models\Organization;
use App\Models\Place;
use App\Models\Slot;
use App\Models\Tag;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Database\Seeder;

use function ceil;
use function collect;
use function min;
use function now;
use function rand;

/**
 * Generate sample/test data
 */
class SampleDataSeeder extends Seeder
{
    public const DATA_SET_MINIMAL = 1;
    public const DATA_SET_STANDARD = 2;
    public const DATA_SET_MAXIMAL = 3;

    public const DATA_SETS = [
        self::DATA_SET_MINIMAL => [
            'admins' => 1,
            'organizers' => 2,
            'normalUsers' => 10,
        ],
        self::DATA_SET_STANDARD => [
            'admins' => 2,
            'organizers' => 4,
            'normalUsers' => 20,
        ],
        self::DATA_SET_MAXIMAL => [
            'admins' => 4,
            'organizers' => 8,
            'normalUsers' => 40,
        ],
    ];

    public int $dataset = self::DATA_SET_STANDARD;

    /**
     * Seed sample data for local testing: users, orgs, events, slots, activities, proposals.
     * All entities get created_by set. Safe to run multiple times (uses firstOrCreate by slug/email).
     */
    public function run(int $dataset = self::DATA_SET_STANDARD): void
    {
        $this->callWith(UserSeeder::class, ['dataset' => self::DATA_SETS[$dataset]]);
        $this->callWith(PlaceSeeder::class, ['dataset' => self::DATA_SETS[$dataset]]);

        $activityTypes = ActivityType::all();
        $tags          = Tag::all();
        $organizers    = User::where('is_event_organizer', 1)->get();
        $allUsers      = User::all();
        $places        = Place::all();

        $organizations = Organization::factory(10)
            ->recycle($organizers)
            ->create();

        Event::factory(30)
            ->public()
            ->recycle($organizations)
            ->recycle($organizers)
            ->recycle($places)
            ->withSameCreatorAsOrganization()
            ->has(EventEnrollmentWindow::factory()->consistentWithEvent())
            ->has(
                Slot::factory(6)
                    ->consistentWithEventAndPlace()
                    ->withActivityTypesAttached($activityTypes)
            )
            ->hasAttached(
                $places
            )
            ->create();

        Activity::factory(100)
            ->recycle($allUsers)
            ->selfHosted($allUsers)
            ->create();

        return;

        // Tag ~60 % of activities (polymorphic taggables)
//        $activities->random((int) ($activities->count() * 0.6))->each(function (Activity $activity) use ($tags) {
//            $activity->tags()->attach(
//                $tags->random(rand(1, 3))->pluck('id')
//            );
//        });

        $slots = collect();

        foreach ($events as $event) {
            $eventSlots = Slot::factory(rand(3, 8))
                ->for($event)
                ->recycle($allPlaces)
                ->recycle($organizers)
                ->create();

            // Assign ~half the slots to an activity
            $eventSlots->random((int) ceil($eventSlots->count() / 2))->each(function (Slot $slot) use ($activities) {
                $slot->update(['activity_id' => $activities->random()->id]);
            });

            // Attach activity types to the remaining open slots
            $eventSlots->whereNull('activity_id')->each(function (Slot $slot) use ($activityTypes) {
                $slot->activityTypes()->attach(
                    $activityTypes->random(rand(1, 2))->pluck('id')
                );
            });

            $slots = $slots->concat($eventSlots);
        }

        // -------------------------------------------------------------------------
        // Activity proposals  (organizers propose activities for events)
        // -------------------------------------------------------------------------

        foreach ($events as $event) {
            $proposalActivities = $activities->random(rand(3, 6));
            $eventSlots         = $slots->where('event_id', $event->id)->values();

            foreach ($proposalActivities as $activity) {
                /** @var ActivityProposal $proposal */
                $proposal = ActivityProposal::factory()
                    ->for($event)
                    ->for($activity)
                    ->for($organizers->random(), 'creator')
                    ->create();

                // Attach 1–3 candidate slots
                $candidateSlots = $eventSlots->random(min(rand(1, 3), $eventSlots->count()));
                $proposal->slots()->attach($candidateSlots->pluck('id'));

                // Accept ~40 % of proposals and wire up the accepted slot
                if ($proposal->status === 'accepted' && $candidateSlots->isNotEmpty()) {
                    $accepted = $candidateSlots->first();
                    $proposal->update(['accepted_slot_id' => $accepted->id]);
                    $accepted->update(['activity_id' => $activity->id]);
                }
            }
        }

        // -------------------------------------------------------------------------
        // Activity participants (activity_user)
        // -------------------------------------------------------------------------

        foreach ($activities as $activity) {
            $max = $activity->max_participants ?? 20;
            $count = rand(1, min($max, $allUsers->count(), 15));

            $participants = $allUsers->random($count);

            foreach ($participants as $user) {
                $activity->participants()->attach($user->id, [
                    'is_absent'  => (bool) rand(0, 4) === 0, // ~20 % absent
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // -------------------------------------------------------------------------
        // Waitlist entries  (for activities that are likely full)
        // -------------------------------------------------------------------------

        $activities->random(10)->each(function (Activity $activity) use ($allUsers) {
            // Pick users not already participating
            $participating = $activity->participants()->pluck('users.id');
            $eligible      = $allUsers->whereNotIn('id', $participating)->values();

            if ($eligible->count() < 2) {
                return;
            }

            $waitlistUsers = $eligible->random(rand(2, min(5, $eligible->count())));
            $position      = 1;

            foreach ($waitlistUsers as $user) {
                ActivityWaitlistEntry::factory()
                    ->for($activity)
                    ->for($user)
                    ->create(['position' => $position++]);
            }
        });

        // -------------------------------------------------------------------------
        // User interests  (wishlist for activities and events)
        // -------------------------------------------------------------------------

        $allUsers->random(20)->each(function (User $user) use ($activities, $events) {
            $user->activityInterests()->attach(
                $activities->random(rand(1, 5))->pluck('id')->unique()
            );
            $user->eventInterests()->attach(
                $events->random(rand(1, 3))->pluck('id')->unique()
            );
        });
    }

    public function getDataSetProperty(string $property): int
    {
        return self::DATA_SETS[$this->dataset][$property];
    }
}
