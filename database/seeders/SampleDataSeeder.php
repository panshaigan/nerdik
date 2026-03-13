<?php

namespace Database\Seeders;

use App\Models\Activity;
use App\Models\Event;
use App\Models\EventInstance;
use App\Models\Organization;
use App\Models\Place;
use App\Models\Slot;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SampleDataSeeder extends Seeder
{
    /**
     * Seed sample data for local testing: users, orgs, events, instances, slots, activities.
     * Safe to run multiple times (uses firstOrCreate by slug/email).
     */
    public function run(): void
    {
        $user1 = User::firstOrCreate(
            ['email' => 'alice@nerdik.test'],
            [
                'name' => 'Alice',
                'nickname' => 'alice',
                'password' => Hash::make('password'),
            ]
        );

        $user2 = User::firstOrCreate(
            ['email' => 'bob@nerdik.test'],
            [
                'name' => 'Bob',
                'nickname' => 'bob',
                'password' => Hash::make('password'),
            ]
        );

        $org = Organization::firstOrCreate(
            ['slug' => 'nerdik-club'],
            [
                'name' => 'Nerdik Club',
                'desc' => 'Local RPG & board game club.',
            ]
        );

        $event1 = Event::firstOrCreate(
            ['slug' => 'monthly-rpg-night'],
            [
                'name' => 'Monthly RPG Night',
                'desc' => 'Regular evening of one-shots and short campaigns.',
                'organization_id' => $org->id,
                'is_public' => true,
                'created_by' => $user1->id,
            ]
        );

        $event2 = Event::firstOrCreate(
            ['slug' => 'convention-2026'],
            [
                'name' => 'Convention 2026',
                'desc' => 'Annual convention with multiple tracks.',
                'organization_id' => null,
                'is_public' => true,
                'created_by' => $user1->id,
            ]
        );

        $wroclaw = Place::where('slug', 'wroclaw')->first();
        $warszawa = Place::where('slug', 'warszawa')->first();

        $now = now();
        $instance1 = EventInstance::firstOrCreate(
            ['slug' => 'monthly-rpg-night-'.($now->format('Y-m'))],
            [
                'event_id' => $event1->id,
                'name' => $now->format('F Y').' edition',
                'starts_at' => $now->copy()->next('Friday')->setTime(18, 0),
                'ends_at' => $now->copy()->next('Friday')->setTime(23, 0),
                'desc' => null,
            ]
        );

        $instance2 = EventInstance::firstOrCreate(
            ['slug' => 'convention-2026-main'],
            [
                'event_id' => $event2->id,
                'name' => 'Main weekend',
                'starts_at' => $now->copy()->addMonths(2)->startOfWeek()->setTime(10, 0),
                'ends_at' => $now->copy()->addMonths(2)->startOfWeek()->addDays(2)->setTime(20, 0),
                'desc' => 'Friday–Sunday.',
            ]
        );

        foreach ([$instance1, $instance2] as $instance) {
            $start = $instance->starts_at;
            if (! $instance->slots()->exists()) {
                Slot::create([
                    'event_instance_id' => $instance->id,
                    'name' => 'Table #01',
                    'starts_at' => $start->copy()->setTime(18, 0),
                    'ends_at' => $start->copy()->setTime(22, 0),
                    'place_id' => $wroclaw?->id ?? $warszawa?->id,
                    'requires_approval' => false,
                    'max_capacity' => 6,
                ]);
                Slot::create([
                    'event_instance_id' => $instance->id,
                    'name' => 'Table #02',
                    'starts_at' => $start->copy()->setTime(18, 0),
                    'ends_at' => $start->copy()->setTime(22, 0),
                    'place_id' => $wroclaw?->id ?? $warszawa?->id,
                    'requires_approval' => true,
                    'max_capacity' => 6,
                ]);
                Slot::create([
                    'event_instance_id' => $instance->id,
                    'name' => 'Table #03',
                    'starts_at' => $start->copy()->setTime(18, 30),
                    'ends_at' => $start->copy()->setTime(21, 30),
                    'place_id' => null,
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
                'host_user_id' => $user1->id,
                'status' => 'planned',
                'duration_minutes' => 240,
                'slug' => 'sample-dnd-one-shot',
            ]
        );

        $activity2 = Activity::firstOrCreate(
            ['slug' => 'sample-forbidden-lands'],
            [
                'name' => 'Forbidden Lands – introductory session',
                'type' => 'rpg',
                'min_participants' => 2,
                'max_participants' => 5,
                'host_user_id' => $user2->id,
                'status' => 'planned',
                'duration_minutes' => 180,
                'slug' => 'sample-forbidden-lands',
            ]
        );

        $slot1 = $instance1->slots()->where('name', 'Table #01')->first();
        $slot2 = $instance1->slots()->where('name', 'Table #03')->first();
        if ($slot1 && ! $slot1->activity_id) {
            $slot1->update(['activity_id' => $activity1->id]);
        }
        if ($slot2 && ! $slot2->activity_id) {
            $slot2->update(['activity_id' => $activity2->id]);
        }
    }
}
