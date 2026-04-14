<?php

namespace Database\Seeders;

use App\Models\ActivityType;
use App\Models\City;
use App\Models\Country;
use App\Models\Event;
use App\Models\EventEnrollmentWindow;
use App\Models\Organization;
use App\Models\Place;
use App\Models\Slot;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Seeder;

use function collect;

class UserSeeder extends Seeder
{
    const ALICE = 'alice@nerdik.test';
    const BOB = 'bob@nerdik.test';
    const CHARLIE = 'charlie@nerdik.test';
    const DIANA = 'diana@nerdik.test';

    /**
     *
     */
    public function run(): void
    {
        $this->seedStandardUsers();
        $this->seedRandomUsers();
    }

    public function seedStandardUsers()
    {
        $f = User::factory();
    }

    private function seedRandomUsers(): void
    {
        // -------------------------------------------------------------------------
        // Already seeded externally: tags (all tag tables), countries, cities,
        // activity_types — we just load them for FK references below.
        // -------------------------------------------------------------------------

        $countries     = Country::all();
        $cities        = City::all();
        $activityTypes = ActivityType::all();
        $tags          = Tag::all();

        // -------------------------------------------------------------------------
        // Users
        // -------------------------------------------------------------------------
        $uf = User::factory();

        $admins = [
            $uf->admin()->email(self::ALICE)->nickname('alice')->create(),
            $uf->admin()->create(),
        ];

        $organizers = [
            $uf->email(self::BOB)->nickname('bob')->eventOrganizer()->create(),
            ...User::factory(4)->eventOrganizer()->create()
        ];

        $participants = [
            $uf->email(self::CHARLIE)->nickname('charlie')->create(),
            $uf->email(self::DIANA)->nickname('diana')->create(),
            ...User::factory(8)->create()
        ];

        $allUsers = [
            ...$admins, ...$organizers, ...$participants
        ];

        // -------------------------------------------------------------------------
        // Organizations  (created by organizers)
        // -------------------------------------------------------------------------

        $organizations = Organization::factory(10)
            ->recycle($organizers)
            ->create();

//        $places = Place::factory(20)->recycle($organizers)->create();
//        return;

        Event::factory(30)
            ->public()
            ->recycle($organizations)
            ->recycle($organizers)
            ->withSameCreatorAsOrganization()
            ->has(EventEnrollmentWindow::factory()->consistentWithEvent())
            ->has(
                Slot::factory(6)
                    ->consistentWithEventAndPlace()
//                    ->hasAttached($activityTypes)
            )
            ->hasAttached(
                Place::factory(1)
                    ->recycle($organizers)
                    ->recycle($cities)
                    ->poland()
                    ->venue()
                    ->consistentWithEvent()
            )
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
}
