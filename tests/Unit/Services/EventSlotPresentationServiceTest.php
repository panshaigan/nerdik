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
}
