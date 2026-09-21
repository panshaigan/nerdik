<?php

namespace Tests\Feature;

use App\Enums\ActivityProposalStatus;
use App\Models\Activity;
use App\Models\ActivityProposal;
use App\Models\ActivityType;
use App\Models\Event;
use App\Models\Slot;
use App\Models\User;
use App\Notifications\ProposalAcceptedNotification;
use App\Services\ActivityProposalDecisionService;
use App\Services\ActivityProposalFlowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ActivityProposalOrganizerAutoAcceptTest extends TestCase
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
            'hosting_mode' => Activity::HOSTING_MODE_DRAFT,
        ];
    }

    public function test_organizer_proposal_is_auto_accepted_into_approval_required_slot(): void
    {
        Notification::fake();

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
        Notification::assertNotSentTo($owner, ProposalAcceptedNotification::class);
    }

    public function test_organizer_proposal_without_preferred_slots_auto_picks_free_slot(): void
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

        app(ActivityProposalFlowService::class)->attachProposedSlotsAndTryAutoAccept(
            $proposal,
            $event,
            $activity,
            [],
        );

        $proposal->refresh();
        $slot->refresh();
        $activity->refresh();

        $this->assertSame(ActivityProposalStatus::Accepted, $proposal->status);
        $this->assertSame($slot->id, (int) $proposal->accepted_slot_id);
        $this->assertSame($activity->id, (int) $slot->activity_id);
        $this->assertSame(Activity::HOSTING_MODE_SCHEDULED_ON_EVENT, (int) $activity->hosting_mode);
    }

    public function test_non_owner_proposal_stays_pending_when_slot_requires_approval(): void
    {
        $owner = User::factory()->create();
        $proposer = User::factory()->create();
        $event = Event::factory()->create(['created_by' => $owner->id]);
        $activity = Activity::factory()->create($this->fittingActivityAttributes($proposer));
        $slot = Slot::factory()->create($this->fittingSlotAttributes($event));

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

        $this->assertSame(ActivityProposalStatus::Pending, $proposal->status);
        $this->assertNull($proposal->accepted_slot_id);
        $this->assertNull($slot->activity_id);
        $this->assertSame(Activity::HOSTING_MODE_PROPOSED_TO_EVENT, (int) $activity->hosting_mode);
    }

    public function test_organizer_proposal_stays_pending_when_no_fitting_free_slot(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->create(['created_by' => $owner->id]);
        $activity = Activity::factory()->create($this->fittingActivityAttributes($owner));

        $proposal = ActivityProposal::factory()->create([
            'activity_id' => $activity->id,
            'event_id' => $event->id,
            'created_by' => $owner->id,
            'status' => ActivityProposalStatus::Pending,
        ]);

        app(ActivityProposalFlowService::class)->attachProposedSlotsAndTryAutoAccept(
            $proposal,
            $event,
            $activity,
            [],
        );

        $proposal->refresh();
        $activity->refresh();

        $this->assertSame(ActivityProposalStatus::Pending, $proposal->status);
        $this->assertNull($proposal->accepted_slot_id);
        $this->assertSame(Activity::HOSTING_MODE_PROPOSED_TO_EVENT, (int) $activity->hosting_mode);
    }

    public function test_accept_notifies_proposer_when_they_are_not_event_owner(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        $proposer = User::factory()->create();
        $event = Event::factory()->create(['created_by' => $owner->id]);
        $activity = Activity::factory()->create($this->fittingActivityAttributes($proposer));
        $slot = Slot::factory()->create($this->fittingSlotAttributes($event));

        $proposal = ActivityProposal::factory()->create([
            'activity_id' => $activity->id,
            'event_id' => $event->id,
            'created_by' => $proposer->id,
            'status' => ActivityProposalStatus::Pending,
        ]);

        app(ActivityProposalDecisionService::class)->accept($proposal, $slot->id);

        Notification::assertSentTo($proposer, ProposalAcceptedNotification::class);
    }
}
