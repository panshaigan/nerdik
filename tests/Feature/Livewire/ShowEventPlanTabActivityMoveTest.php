<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Livewire\Events\EventShowPlanTab;
use App\Models\Activity;
use App\Models\ActivityType;
use App\Models\Event;
use App\Models\Slot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ShowEventPlanTabActivityMoveTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function fittingSlotAttributes(Event $event, ?int $activityId = null): array
    {
        return [
            'event_id' => $event->id,
            'activity_id' => $activityId,
            'requires_approval' => true,
            'starts_at' => null,
            'ends_at' => null,
            'max_capacity' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fittingActivityAttributes(User $owner): array
    {
        $rpgTypeId = ActivityType::query()->where('slug', ActivityType::SLUG_RPG)->value('id')
            ?? ActivityType::factory()->create(['slug' => ActivityType::SLUG_RPG])->id;

        return [
            'created_by' => $owner->id,
            'activity_type_id' => $rpgTypeId,
            'duration_in_minutes' => null,
            'max_participants' => null,
            'min_participants' => null,
            'is_host_passive' => false,
            'hosting_mode' => Activity::HOSTING_MODE_SCHEDULED_ON_EVENT,
        ];
    }

    public function test_organizer_can_move_activity_to_empty_slot_via_livewire(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->public()->create(['created_by' => $owner->id]);
        $activity = Activity::factory()->create($this->fittingActivityAttributes($owner));
        $source = Slot::factory()->create($this->fittingSlotAttributes($event, $activity->id));
        $target = Slot::factory()->create($this->fittingSlotAttributes($event));

        Livewire::withoutLazyLoading()
            ->actingAs($owner)
            ->test(EventShowPlanTab::class, ['eventId' => $event->id])
            ->call('moveActivityToSlot', $activity->id, 0, $target->id);

        $this->assertNull($source->fresh()->activity_id);
        $this->assertSame($activity->id, (int) $target->fresh()->activity_id);
    }

    public function test_organizer_can_swap_activities_between_slots(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->public()->create(['created_by' => $owner->id]);
        $activityA = Activity::factory()->create($this->fittingActivityAttributes($owner));
        $activityB = Activity::factory()->create($this->fittingActivityAttributes($owner));
        $slotA = Slot::factory()->create($this->fittingSlotAttributes($event, $activityA->id));
        $slotB = Slot::factory()->create($this->fittingSlotAttributes($event, $activityB->id));

        Livewire::withoutLazyLoading()
            ->actingAs($owner)
            ->test(EventShowPlanTab::class, ['eventId' => $event->id])
            ->call('moveActivityToSlot', $activityA->id, 0, $slotB->id);

        $this->assertSame($activityB->id, (int) $slotA->fresh()->activity_id);
        $this->assertSame($activityA->id, (int) $slotB->fresh()->activity_id);
    }

    public function test_move_rejects_unauthorized_viewer(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $event = Event::factory()->public()->create(['created_by' => $owner->id]);
        $activity = Activity::factory()->create($this->fittingActivityAttributes($owner));
        $source = Slot::factory()->create($this->fittingSlotAttributes($event, $activity->id));
        $target = Slot::factory()->create($this->fittingSlotAttributes($event));

        Livewire::withoutLazyLoading()
            ->actingAs($viewer)
            ->test(EventShowPlanTab::class, ['eventId' => $event->id])
            ->call('moveActivityToSlot', $activity->id, 0, $target->id)
            ->assertForbidden();

        $this->assertSame($activity->id, (int) $source->fresh()->activity_id);
        $this->assertNull($target->fresh()->activity_id);
    }

    public function test_move_shows_validation_error_without_mutating_slots(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->public()->create(['created_by' => $owner->id]);
        $activity = Activity::factory()->create(array_merge(
            $this->fittingActivityAttributes($owner),
            ['duration_in_minutes' => 180],
        ));
        $source = Slot::factory()->create($this->fittingSlotAttributes($event, $activity->id));
        $target = Slot::factory()->create(array_merge(
            $this->fittingSlotAttributes($event),
            [
                'starts_at' => now()->addDay(),
                'ends_at' => now()->addDay()->addHour(),
            ],
        ));

        Livewire::withoutLazyLoading()
            ->actingAs($owner)
            ->test(EventShowPlanTab::class, ['eventId' => $event->id])
            ->call('moveActivityToSlot', $activity->id, 0, $target->id)
            ->assertHasErrors('moveActivityToSlot');

        $this->assertSame($activity->id, (int) $source->fresh()->activity_id);
        $this->assertNull($target->fresh()->activity_id);
    }
}
