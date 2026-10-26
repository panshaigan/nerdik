<?php

namespace Tests\Unit\Services;

use App\Enums\ActivityProposalStatus;
use App\Models\Activity;
use App\Models\ActivityProposal;
use App\Models\ActivityType;
use App\Models\Event;
use App\Models\Slot;
use App\Models\User;
use App\Notifications\ProposalRejectedNotification;
use App\Services\ActivityProposalDecisionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ActivityProposalDecisionServiceTest extends TestCase
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
            'hosting_mode' => Activity::HOSTING_MODE_PROPOSED_TO_EVENT,
        ];
    }

    #[Test]
    public function accept_assigns_activity_to_chosen_free_slot(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->create(['created_by' => $owner->id]);
        $activity = Activity::factory()->create($this->fittingActivityAttributes($owner));
        $slot = Slot::factory()->create($this->fittingSlotAttributes($event));

        $proposal = ActivityProposal::factory()->create([
            'activity_id' => $activity->id,
            'event_id' => $event->id,
            'created_by' => $owner->id,
            'status' => ActivityProposalStatus::Pending,
        ]);

        app(ActivityProposalDecisionService::class)->accept($proposal, $slot->id);

        $proposal->refresh();
        $slot->refresh();
        $activity->refresh();

        $this->assertSame(ActivityProposalStatus::Accepted, $proposal->status);
        $this->assertSame($slot->id, (int) $proposal->accepted_slot_id);
        $this->assertSame($activity->id, (int) $slot->activity_id);
        $this->assertSame(Activity::HOSTING_MODE_SCHEDULED_ON_EVENT, (int) $activity->hosting_mode);
    }

    #[Test]
    public function reject_marks_proposal_rejected_and_returns_activity_to_draft(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        $proposer = User::factory()->create();
        $event = Event::factory()->create(['created_by' => $owner->id]);
        $activity = Activity::factory()->create($this->fittingActivityAttributes($proposer));
        $proposal = ActivityProposal::factory()->create([
            'activity_id' => $activity->id,
            'event_id' => $event->id,
            'created_by' => $proposer->id,
            'status' => ActivityProposalStatus::Pending,
        ]);

        app(ActivityProposalDecisionService::class)->reject($proposal);

        $proposal->refresh();
        $activity->refresh();

        $this->assertSame(ActivityProposalStatus::Rejected, $proposal->status);
        $this->assertNull($proposal->accepted_slot_id);
        $this->assertSame(Activity::HOSTING_MODE_DRAFT, (int) $activity->hosting_mode);
        Notification::assertSentTo($proposer, ProposalRejectedNotification::class);
    }
}
