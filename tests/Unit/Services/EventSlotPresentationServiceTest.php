<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Event;
use App\Models\Slot;
use App\Models\User;
use App\Services\EventSlotPresentationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventSlotPresentationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_slot_hour_groups_sort_slots_by_id_asc_within_each_group(): void
    {
        $service = new EventSlotPresentationService;
        $user = User::factory()->create();
        $startsAt = Carbon::parse('2026-06-12 14:30:00', 'UTC');

        $event = Event::factory()->public()->create([
            'created_by' => $user->id,
            'starts_at' => $startsAt->copy()->subDay(),
            'ends_at' => $startsAt->copy()->addDay(),
        ]);

        $olderSlot = Slot::factory()->create([
            'event_id' => $event->id,
            'name' => 'Slot Alpha',
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHour(),
            'created_by' => $user->id,
        ]);

        $newerSlot = Slot::factory()->create([
            'event_id' => $event->id,
            'name' => 'Slot Beta',
            'starts_at' => $startsAt->copy()->addMinutes(15),
            'ends_at' => $startsAt->copy()->addHour(),
            'created_by' => $user->id,
        ]);

        $event->load('slots');
        $groups = $service->slotHourGroupsForEvent($event);

        $slotGroup = collect($groups)->first(
            fn (array $group): bool => $group['slots']->isNotEmpty(),
        );

        $this->assertNotNull($slotGroup);

        $orderedIds = $slotGroup['slots']->pluck('id')->map(fn ($id) => (int) $id)->all();
        $this->assertSame([(int) $olderSlot->id, (int) $newerSlot->id], $orderedIds);
    }

    public function test_omits_event_start_boundary_when_equal_to_first_slot_start(): void
    {
        $service = new EventSlotPresentationService;
        $user = User::factory()->create();
        $startsAt = Carbon::parse('2026-06-12 14:00:00', 'UTC');
        $endsAt = Carbon::parse('2026-06-12 18:00:00', 'UTC');

        $event = Event::factory()->public()->create([
            'created_by' => $user->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);

        Slot::factory()->create([
            'event_id' => $event->id,
            'name' => 'Opening slot',
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHour(),
            'created_by' => $user->id,
        ]);

        $event->load('slots');
        $groups = $service->slotHourGroupsForEvent($event);

        $this->assertFalse(
            collect($groups)->contains(fn (array $group): bool => ($group['boundary'] ?? null) === 'event_start'),
        );
        $this->assertTrue(
            collect($groups)->contains(fn (array $group): bool => ($group['boundary'] ?? null) === 'event_end'),
        );
    }

    public function test_includes_event_start_boundary_when_before_first_slot_start(): void
    {
        $service = new EventSlotPresentationService;
        $user = User::factory()->create();
        $eventStartsAt = Carbon::parse('2026-06-12 13:00:00', 'UTC');
        $slotStartsAt = Carbon::parse('2026-06-12 14:00:00', 'UTC');
        $endsAt = Carbon::parse('2026-06-12 18:00:00', 'UTC');

        $event = Event::factory()->public()->create([
            'created_by' => $user->id,
            'starts_at' => $eventStartsAt,
            'ends_at' => $endsAt,
        ]);

        Slot::factory()->create([
            'event_id' => $event->id,
            'name' => 'Later slot',
            'starts_at' => $slotStartsAt,
            'ends_at' => $slotStartsAt->copy()->addHour(),
            'created_by' => $user->id,
        ]);

        $event->load('slots');
        $groups = $service->slotHourGroupsForEvent($event);

        $this->assertTrue(
            collect($groups)->contains(fn (array $group): bool => ($group['boundary'] ?? null) === 'event_start'),
        );
    }

    public function test_omits_event_end_boundary_when_equal_to_last_slot_end(): void
    {
        $service = new EventSlotPresentationService;
        $user = User::factory()->create();
        $startsAt = Carbon::parse('2026-06-12 14:00:00', 'UTC');
        $endsAt = Carbon::parse('2026-06-12 18:00:00', 'UTC');

        $event = Event::factory()->public()->create([
            'created_by' => $user->id,
            'starts_at' => $startsAt->copy()->subHour(),
            'ends_at' => $endsAt,
        ]);

        Slot::factory()->create([
            'event_id' => $event->id,
            'name' => 'Closing slot',
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'created_by' => $user->id,
        ]);

        $event->load('slots');
        $groups = $service->slotHourGroupsForEvent($event);

        $this->assertFalse(
            collect($groups)->contains(fn (array $group): bool => ($group['boundary'] ?? null) === 'event_end'),
        );
        $this->assertTrue(
            collect($groups)->contains(fn (array $group): bool => ($group['boundary'] ?? null) === 'event_start'),
        );
    }
}
