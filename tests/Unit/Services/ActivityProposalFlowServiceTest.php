<?php

namespace Tests\Unit\Services;

use App\Enums\ActivityProposalStatus;
use App\Models\Activity;
use App\Models\ActivityProposal;
use App\Models\ActivityType;
use App\Models\Event;
use App\Models\Slot;
use App\Models\User;
use App\Services\ActivityProposalFlowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ActivityProposalFlowServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function fittingSlotAttributes(Event $event, bool $requiresApproval = false): array
    {
        return [
            'event_id' => $event->id,
            'activity_id' => null,
            'requires_approval' => $requiresApproval,
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
            'hosting_mode' => Activity::HOSTING_MODE_DRAFT,
        ];
    }

    #[Test]
    public function auto_accepts_when_preferred_free_slot_does_not_require_approval(): void
    {
        $owner = User::factory()->create();
        $proposer = User::factory()->create();
        $event = Event::factory()->create(['created_by' => $owner->id]);
        $activity = Activity::factory()->create($this->fittingActivityAttributes($proposer));
        $slot = Slot::factory()->create($this->fittingSlotAttributes($event, requiresApproval: false));

        $proposal = ActivityProposal::factory()->create([
            'activity_id' => $activity->id,
            'event_id' => $event->id,
            'created_by' => $proposer->id,
            'status' => ActivityProposalStatus::Pending,
        ]);

        app(ActivityProposalFlowService::class)->attachProposedSlotsAndTryAutoAccept(
            $proposal,
            $event,
            $activity,
            [$slot->id],
        );

        $proposal->refresh();
        $slot->refresh();
        $activity->refresh();

        $this->assertSame(ActivityProposalStatus::Accepted, $proposal->status);
        $this->assertSame($slot->id, (int) $proposal->accepted_slot_id);
        $this->assertSame($activity->id, (int) $slot->activity_id);
        $this->assertSame(Activity::HOSTING_MODE_SCHEDULED_ON_EVENT, (int) $activity->hosting_mode);
    }

    #[Test]
    public function remains_pending_when_preferred_slots_only_require_manual_approval(): void
    {
        $owner = User::factory()->create();
        $proposer = User::factory()->create();
        $event = Event::factory()->create(['created_by' => $owner->id]);
        $activity = Activity::factory()->create($this->fittingActivityAttributes($proposer));
        $slot = Slot::factory()->create($this->fittingSlotAttributes($event, requiresApproval: true));

        $proposal = ActivityProposal::factory()->create([
            'activity_id' => $activity->id,
            'event_id' => $event->id,
            'created_by' => $proposer->id,
            'status' => ActivityProposalStatus::Pending,
        ]);

        app(ActivityProposalFlowService::class)->attachProposedSlotsAndTryAutoAccept(
            $proposal,
            $event,
            $activity,
            [$slot->id],
        );

        $proposal->refresh();
        $slot->refresh();

        $this->assertSame(ActivityProposalStatus::Pending, $proposal->status);
        $this->assertNull($slot->activity_id);
        $this->assertTrue($proposal->proposedSlots()->whereKey($slot->id)->exists());
    }
}
