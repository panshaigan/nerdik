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
use Illuminate\Validation\ValidationException;
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

    #[Test]
    public function accept_with_null_slot_id_auto_selects_fitting_free_slot(): void
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

        app(ActivityProposalDecisionService::class)->accept($proposal, null);

        $proposal->refresh();
        $slot->refresh();
        $activity->refresh();

        $this->assertSame(ActivityProposalStatus::Accepted, $proposal->status);
        $this->assertSame($slot->id, (int) $proposal->accepted_slot_id);
        $this->assertSame($activity->id, (int) $slot->activity_id);
        $this->assertSame(Activity::HOSTING_MODE_SCHEDULED_ON_EVENT, (int) $activity->hosting_mode);
    }

    #[Test]
    public function accept_with_null_slot_id_fails_when_no_fitting_slot(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->create(['created_by' => $owner->id]);
        $activity = Activity::factory()->create(array_merge(
            $this->fittingActivityAttributes($owner),
            ['duration_in_minutes' => 180],
        ));
        Slot::factory()->create(array_merge(
            $this->fittingSlotAttributes($event),
            [
                'starts_at' => now()->addDay(),
                'ends_at' => now()->addDay()->addHour(),
            ],
        ));

        $proposal = ActivityProposal::factory()->create([
            'activity_id' => $activity->id,
            'event_id' => $event->id,
            'created_by' => $owner->id,
            'status' => ActivityProposalStatus::Pending,
        ]);

        try {
            app(ActivityProposalDecisionService::class)->accept($proposal, null);
            $this->fail('Expected ValidationException when no fitting slot exists.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('slot_id', $e->errors());
            $this->assertSame(
                __('ui.status.no_fitting_slot_for_proposal'),
                $e->errors()['slot_id'][0],
            );
        }

        $this->assertSame(ActivityProposalStatus::Pending, $proposal->fresh()->status);
    }

    #[Test]
    public function accept_with_explicit_slot_rejects_duration_mismatch(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->create(['created_by' => $owner->id]);
        $activity = Activity::factory()->create(array_merge(
            $this->fittingActivityAttributes($owner),
            ['duration_in_minutes' => 180],
        ));
        $slot = Slot::factory()->create(array_merge(
            $this->fittingSlotAttributes($event),
            [
                'starts_at' => now()->addDay(),
                'ends_at' => now()->addDay()->addHour(),
            ],
        ));

        $proposal = ActivityProposal::factory()->create([
            'activity_id' => $activity->id,
            'event_id' => $event->id,
            'created_by' => $owner->id,
            'status' => ActivityProposalStatus::Pending,
        ]);

        try {
            app(ActivityProposalDecisionService::class)->accept($proposal, $slot->id);
            $this->fail('Expected ValidationException for duration mismatch.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('slot_id', $e->errors());
            $this->assertSame(
                __('ui.status.slot_activity_type_or_duration_mismatch'),
                $e->errors()['slot_id'][0],
            );
        }

        $this->assertSame(ActivityProposalStatus::Pending, $proposal->fresh()->status);
        $this->assertNull($slot->fresh()->activity_id);
    }
}
