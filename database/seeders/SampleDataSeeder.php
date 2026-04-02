<?php

namespace Database\Seeders;

use App\Models\Activity;
use App\Models\ActivityProposal;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Place;
use App\Models\Slot;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SampleDataSeeder extends Seeder
{
    /**
     * Seed sample data for local testing: users, orgs, events, slots, activities, proposals.
     * All entities get created_by set. Safe to run multiple times (uses firstOrCreate by slug/email).
     */
    public function run(): void
    {
        $alice = User::firstOrCreate(
            ['email' => 'alice@nerdik.test'],
            [
                'name' => 'Alice',
                'nickname' => 'alice',
                'password' => Hash::make('password'),
                'is_admin' => true,
            ]
        );

        $bob = User::firstOrCreate(
            ['email' => 'bob@nerdik.test'],
            [
                'name' => 'Bob',
                'nickname' => 'bob',
                'password' => Hash::make('password'),
            ]
        );

        $charlie = User::firstOrCreate(
            ['email' => 'charlie@nerdik.test'],
            [
                'name' => 'Charlie',
                'nickname' => 'charlie',
                'password' => Hash::make('password'),
            ]
        );

        $diana = User::firstOrCreate(
            ['email' => 'diana@nerdik.test'],
            [
                'name' => 'Diana',
                'nickname' => 'diana',
                'password' => Hash::make('password'),
            ]
        );

        $org = Organization::firstOrCreate(
            ['slug' => 'nerdik-club'],
            [
                'name' => 'Nerdik Club',
                'desc' => 'Local RPG & board game club.',
                'created_by' => $alice->id,
            ]
        );

        $org2 = Organization::firstOrCreate(
            ['slug' => 'wroclaw-gamers'],
            [
                'name' => 'Wrocław Gamers',
                'desc' => 'Community for tabletop gamers in Wrocław.',
                'created_by' => $bob->id,
            ]
        );

        $now = now();

        $event1 = Event::firstOrCreate(
            ['slug' => 'monthly-rpg-night-'.($now->format('Y-m'))],
            [
                'name' => 'Monthly RPG Night',
                'desc' => 'Regular evening of one-shots and short campaigns.',
                'organization_id' => $org->id,
                'is_public' => true,
                'created_by' => $alice->id,
                'starts_at' => $now->copy()->next('Friday')->setTime(18, 0),
                'ends_at' => $now->copy()->next('Friday')->setTime(23, 0),
            ]
        );

        $event2 = Event::firstOrCreate(
            ['slug' => 'convention-2026-main'],
            [
                'name' => 'Convention 2026',
                'desc' => 'Annual convention with multiple tracks.',
                'organization_id' => null,
                'is_public' => true,
                'created_by' => $alice->id,
                'starts_at' => $now->copy()->addMonths(2)->startOfWeek()->setTime(10, 0),
                'ends_at' => $now->copy()->addMonths(2)->startOfWeek()->addDays(2)->setTime(20, 0),
            ]
        );

        $event3 = Event::firstOrCreate(
            ['slug' => 'board-game-evening-'.($now->format('Y-m-d'))],
            [
                'name' => 'Board Game Evening',
                'desc' => 'Casual board game night at the club.',
                'organization_id' => $org2->id,
                'is_public' => true,
                'created_by' => $bob->id,
                'starts_at' => $now->copy()->next('Wednesday')->setTime(17, 0),
                'ends_at' => $now->copy()->next('Wednesday')->setTime(22, 0),
            ]
        );

        $wroclaw = Place::where('slug', 'wroclaw')->first();
        $warszawa = Place::where('slug', 'warszawa')->first();
        $placeId = $wroclaw?->id ?? $warszawa?->id;

        $organizerForEvent = fn (Event $e) => $e->created_by;

        foreach ([$event1, $event2, $event3] as $event) {
            $start = $event->starts_at;
            $createdBy = $organizerForEvent($event);
            if (! $event->slots()->exists()) {
                $s1 = Slot::create([
                    'event_id' => $event->id,
                    'created_by' => $createdBy,
                    'name' => 'Table #01',
                    'starts_at' => $start->copy()->setTime(18, 0),
                    'ends_at' => $start->copy()->setTime(22, 0),
                    'requires_approval' => false,
                    'max_capacity' => 6,
                ]);
                if ($placeId) {
                    $s1->places()->attach($placeId);
                }
                $s2 = Slot::create([
                    'event_id' => $event->id,
                    'created_by' => $createdBy,
                    'name' => 'Table #02',
                    'starts_at' => $start->copy()->setTime(18, 0),
                    'ends_at' => $start->copy()->setTime(22, 0),
                    'requires_approval' => true,
                    'max_capacity' => 6,
                ]);
                if ($placeId) {
                    $s2->places()->attach($placeId);
                }
                Slot::create([
                    'event_id' => $event->id,
                    'created_by' => $createdBy,
                    'name' => 'Table #03',
                    'starts_at' => $start->copy()->setTime(18, 30),
                    'ends_at' => $start->copy()->setTime(21, 30),
                    'requires_approval' => false,
                    'max_capacity' => 4,
                ]);
            }
        }

        $activity1 = Activity::firstOrCreate(
            ['slug' => 'sample-dnd-one-shot'],
            [
                'name' => 'D&D 5e one-shot: Lost Mine',
                'type' => 'rpg',
                'min_participants' => 2,
                'max_participants' => 5,
                'host_user_id' => $alice->id,
                'created_by' => $alice->id,
                'status' => 'planned',
                'duration_minutes' => 240,
            ]
        );

        $activity2 = Activity::firstOrCreate(
            ['slug' => 'sample-forbidden-lands'],
            [
                'name' => 'Forbidden Lands – introductory session',
                'type' => 'rpg',
                'min_participants' => 2,
                'max_participants' => 5,
                'host_user_id' => $bob->id,
                'created_by' => $bob->id,
                'status' => 'planned',
                'duration_minutes' => 180,
            ]
        );

        $activity3 = Activity::firstOrCreate(
            ['slug' => 'sample-talisman'],
            [
                'name' => 'Talisman – board game open table',
                'type' => 'board',
                'min_participants' => 2,
                'max_participants' => 6,
                'host_user_id' => $charlie->id,
                'created_by' => $charlie->id,
                'status' => 'planned',
                'duration_minutes' => 180,
            ]
        );

        $activity4 = Activity::firstOrCreate(
            ['slug' => 'sample-call-of-cthulhu'],
            [
                'name' => 'Call of Cthulhu one-shot',
                'type' => 'rpg',
                'min_participants' => 2,
                'max_participants' => 5,
                'host_user_id' => $diana->id,
                'created_by' => $diana->id,
                'status' => 'planned',
                'duration_minutes' => 240,
            ]
        );

        $slot1 = $event1->slots()->where('name', 'Table #01')->first();
        $slot2 = $event1->slots()->where('name', 'Table #03')->first();
        if ($slot1 && ! $slot1->activity_id) {
            $slot1->update(['activity_id' => $activity1->id]);
        }
        if ($slot2 && ! $slot2->activity_id) {
            $slot2->update(['activity_id' => $activity2->id]);
        }

        $slotBoard = $event3->slots()->where('name', 'Table #01')->first();
        if ($slotBoard && ! $slotBoard->activity_id) {
            $slotBoard->update(['activity_id' => $activity3->id]);
        }

        // Pending proposal: Diana proposes her activity to the convention (event2)
        $freeSlotConvention = $event2->slots()->whereNull('activity_id')->first();
        if ($freeSlotConvention && ! ActivityProposal::where('activity_id', $activity4->id)->where('event_id', $event2->id)->where('status', 'pending')->exists()) {
            $proposal = ActivityProposal::create([
                'activity_id' => $activity4->id,
                'event_id' => $event2->id,
                'created_by' => $diana->id,
                'status' => 'pending',
            ]);
            $proposal->proposedSlots()->sync([$freeSlotConvention->id]);
        }

        // Sample tags on events & activities (TagSeeder must run first)
        $t = fn (string $slug) => Tag::where('slug', $slug)->first();
        if ($t('dungeons-and-dragons-5e')) {
            $event1->tags()->syncWithoutDetaching([$t('dungeons-and-dragons-5e')->id]);
            $activity1->tags()->syncWithoutDetaching([$t('dungeons-and-dragons-5e')->id]);
        }
        if ($t('forbidden-lands')) {
            $event1->tags()->syncWithoutDetaching([$t('forbidden-lands')->id]);
            $activity2->tags()->syncWithoutDetaching([$t('forbidden-lands')->id]);
        }
        if ($t('horror')) {
            $event2->tags()->syncWithoutDetaching([$t('horror')->id]);
        }
        if ($t('call-of-cthulhu')) {
            $activity4->tags()->syncWithoutDetaching([$t('call-of-cthulhu')->id]);
        }
        if ($t('violence')) {
            $activity1->tags()->syncWithoutDetaching([$t('violence')->id]);
        }
    }
}
