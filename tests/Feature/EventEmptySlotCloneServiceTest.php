<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\ActivityType;
use App\Models\Event;
use App\Models\Slot;
use App\Models\User;
use App\Services\EventEmptySlotCloneService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventEmptySlotCloneServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_clones_slots_without_activities_and_preserves_activity_type_restrictions(): void
    {
        $user = User::factory()->create();
        $source = Event::factory()->create([
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'organization_id' => null,
        ]);
        $target = Event::factory()->create([
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'organization_id' => null,
        ]);

        $activity = Activity::factory()->create([
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'activity_type_id' => ActivityType::factory()->create()->id,
        ]);

        $slot = Slot::factory()->create([
            'event_id' => $source->id,
            'activity_id' => $activity->id,
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'name' => 'Table 1',
            'requires_approval' => true,
            'max_capacity' => 6,
        ]);
        $typeId = (int) $activity->activity_type_id;
        $this->assertGreaterThan(0, $typeId);
        $slot->setActivityTypes([$typeId]);

        app(EventEmptySlotCloneService::class)->cloneEmptySlots($source, $target);

        $this->assertSame(1, Slot::query()->where('event_id', $target->id)->count());
        $cloned = Slot::query()->where('event_id', $target->id)->firstOrFail();
        $this->assertNull($cloned->activity_id);
        $this->assertSame('Table 1', $cloned->name);
        $this->assertTrue($cloned->requires_approval);
        $this->assertSame(6, (int) $cloned->max_capacity);
        $this->assertSame([$typeId], $cloned->activity_types_ids);
    }

    public function test_shifts_slot_datetimes_by_event_starts_at_delta(): void
    {
        $user = User::factory()->create();
        $sourceStartsAt = Carbon::parse('2026-01-10 10:00:00', 'UTC');
        $targetStartsAt = Carbon::parse('2026-02-10 10:00:00', 'UTC');

        $source = Event::factory()->create([
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'organization_id' => null,
            'starts_at' => $sourceStartsAt,
            'ends_at' => $sourceStartsAt->copy()->addDay(),
        ]);
        $target = Event::factory()->create([
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'organization_id' => null,
            'starts_at' => $targetStartsAt,
            'ends_at' => $targetStartsAt->copy()->addDay(),
        ]);

        Slot::factory()->create([
            'event_id' => $source->id,
            'activity_id' => null,
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'starts_at' => $sourceStartsAt->copy()->addHours(2),
            'ends_at' => $sourceStartsAt->copy()->addHours(4),
        ]);

        app(EventEmptySlotCloneService::class)->cloneEmptySlots($source, $target);

        $cloned = Slot::query()->where('event_id', $target->id)->firstOrFail();
        $this->assertTrue($cloned->starts_at->equalTo($targetStartsAt->copy()->addHours(2)));
        $this->assertTrue($cloned->ends_at->equalTo($targetStartsAt->copy()->addHours(4)));
    }

    public function test_keeps_slot_datetimes_when_event_starts_at_unchanged(): void
    {
        $user = User::factory()->create();
        $startsAt = Carbon::parse('2026-01-10 10:00:00', 'UTC');
        $slotStartsAt = $startsAt->copy()->addHours(2);
        $slotEndsAt = $startsAt->copy()->addHours(4);

        $source = Event::factory()->create([
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'organization_id' => null,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addDay(),
        ]);
        $target = Event::factory()->create([
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'organization_id' => null,
            'starts_at' => $startsAt->copy(),
            'ends_at' => $startsAt->copy()->addDay(),
        ]);

        Slot::factory()->create([
            'event_id' => $source->id,
            'activity_id' => null,
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'starts_at' => $slotStartsAt,
            'ends_at' => $slotEndsAt,
        ]);

        app(EventEmptySlotCloneService::class)->cloneEmptySlots($source, $target);

        $cloned = Slot::query()->where('event_id', $target->id)->firstOrFail();
        $this->assertTrue($cloned->starts_at->equalTo($slotStartsAt));
        $this->assertTrue($cloned->ends_at->equalTo($slotEndsAt));
    }
}
