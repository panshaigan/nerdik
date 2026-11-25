<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\ActivityProposalStatus;
use App\Models\Activity;
use App\Models\ActivityProposal;
use App\Models\ActivityType;
use App\Models\Event;
use App\Models\Slot;
use App\Models\User;
use App\Services\ActivitySlotAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ActivitySlotAssignmentServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function fittingSlotAttributes(Event $event): array
    {
        return [
            'event_id' => $event->id,
            'activity_id' => null,
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

    #[Test]
    public function move_assigns_activity_to_empty_target_slot(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->create(['created_by' => $owner->id]);
        $activity = Activity::factory()->create($this->fittingActivityAttributes($owner));
        $source = Slot::factory()->create(array_merge(
            $this->fittingSlotAttributes($event),
            ['activity_id' => $activity->id],
        ));
        $target = Slot::factory()->create($this->fittingSlotAttributes($event));

        $result = app(ActivitySlotAssignmentService::class)->moveOrSwap($event, $source, $target);

        $this->assertSame(ActivitySlotAssignmentService::RESULT_MOVE, $result);
        $this->assertNull($source->fresh()->activity_id);
        $this->assertSame($activity->id, (int) $target->fresh()->activity_id);
        $this->assertSame(Activity::HOSTING_MODE_SCHEDULED_ON_EVENT, (int) $activity->fresh()->hosting_mode);
    }

    #[Test]
    public function swap_exchanges_two_scheduled_activities(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->create(['created_by' => $owner->id]);
        $activityA = Activity::factory()->create($this->fittingActivityAttributes($owner));
        $activityB = Activity::factory()->create($this->fittingActivityAttributes($owner));
        $slotA = Slot::factory()->create(array_merge(
            $this->fittingSlotAttributes($event),
            ['activity_id' => $activityA->id],
        ));
        $slotB = Slot::factory()->create(array_merge(
            $this->fittingSlotAttributes($event),
            ['activity_id' => $activityB->id],
        ));

        $result = app(ActivitySlotAssignmentService::class)->moveOrSwap($event, $slotA, $slotB);

        $this->assertSame(ActivitySlotAssignmentService::RESULT_SWAP, $result);
        $this->assertSame($activityB->id, (int) $slotA->fresh()->activity_id);
        $this->assertSame($activityA->id, (int) $slotB->fresh()->activity_id);
    }

    #[Test]
    public function move_updates_accepted_proposal_slot_id(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->create(['created_by' => $owner->id]);
        $activity = Activity::factory()->create($this->fittingActivityAttributes($owner));
        $source = Slot::factory()->create(array_merge(
            $this->fittingSlotAttributes($event),
            ['activity_id' => $activity->id],
        ));
        $target = Slot::factory()->create($this->fittingSlotAttributes($event));

        $proposal = ActivityProposal::factory()->create([
            'activity_id' => $activity->id,
            'event_id' => $event->id,
            'created_by' => $owner->id,
            'status' => ActivityProposalStatus::Accepted,
            'accepted_slot_id' => $source->id,
        ]);

        app(ActivitySlotAssignmentService::class)->moveOrSwap($event, $source, $target);

        $this->assertSame($target->id, (int) $proposal->fresh()->accepted_slot_id);
        $this->assertSame(ActivityProposalStatus::Accepted, $proposal->fresh()->status);
    }

    #[Test]
    public function move_rejects_when_activity_does_not_fit_target_slot(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->create(['created_by' => $owner->id]);
        $activity = Activity::factory()->create(array_merge(
            $this->fittingActivityAttributes($owner),
            ['duration_in_minutes' => 180],
        ));
        $source = Slot::factory()->create(array_merge(
            $this->fittingSlotAttributes($event),
            ['activity_id' => $activity->id],
        ));
        $target = Slot::factory()->create(array_merge(
            $this->fittingSlotAttributes($event),
            [
                'starts_at' => now()->addDay(),
                'ends_at' => now()->addDay()->addHour(),
            ],
        ));

        try {
            app(ActivitySlotAssignmentService::class)->moveOrSwap($event, $source, $target);
            $this->fail('Expected ValidationException for duration mismatch.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('slot_id', $e->errors());
        }

        $this->assertSame($activity->id, (int) $source->fresh()->activity_id);
        $this->assertNull($target->fresh()->activity_id);
    }

    #[Test]
    public function move_or_swap_noops_when_source_equals_target(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->create(['created_by' => $owner->id]);
        $activity = Activity::factory()->create($this->fittingActivityAttributes($owner));
        $slot = Slot::factory()->create(array_merge(
            $this->fittingSlotAttributes($event),
            ['activity_id' => $activity->id],
        ));

        $result = app(ActivitySlotAssignmentService::class)->moveOrSwap($event, $slot, $slot);

        $this->assertSame(ActivitySlotAssignmentService::RESULT_NOOP, $result);
    }

    #[Test]
    public function move_rejects_when_source_has_no_activity(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->create(['created_by' => $owner->id]);
        $source = Slot::factory()->create($this->fittingSlotAttributes($event));
        $target = Slot::factory()->create($this->fittingSlotAttributes($event));

        try {
            app(ActivitySlotAssignmentService::class)->moveOrSwap($event, $source, $target);
            $this->fail('Expected ValidationException when source slot is empty.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('activity_id', $e->errors());
        }
    }
}
