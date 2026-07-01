<?php

namespace Tests\Feature;

use App\Enums\ActivityProposalStatus;
use App\Enums\ParticipationMode;
use App\Models\Activity;
use App\Models\ActivityProposal;
use App\Models\ActivityType;
use App\Models\Event;
use App\Models\Slot;
use App\Models\User;
use App\Services\ActivityProposalDecisionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SlotForcedParticipationAttachTest extends TestCase
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
        ];
    }

    public function test_accept_rejects_activity_with_mismatched_participation(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->create(['created_by' => $owner->id]);
        $activity = Activity::factory()->create(array_merge($this->fittingActivityAttributes($owner), [
            'participation_mode' => ParticipationMode::Open,
            'allows_observers' => false,
        ]));

        $slot = Slot::factory()->forcesParticipation(ParticipationMode::HostApproval)->create(
            $this->fittingSlotAttributes($event),
        );

        $proposal = ActivityProposal::factory()->create([
            'activity_id' => $activity->id,
            'event_id' => $event->id,
            'created_by' => $owner->id,
            'status' => ActivityProposalStatus::Pending,
        ]);

        $this->expectException(ValidationException::class);

        app(ActivityProposalDecisionService::class)->accept($proposal, $slot->id);
    }

    public function test_accept_succeeds_and_keeps_matching_forced_lottery_settings(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->create(['created_by' => $owner->id]);
        $activity = Activity::factory()->create(array_merge($this->fittingActivityAttributes($owner), [
            'participation_mode' => ParticipationMode::Lottery,
            'lottery_draw_in_hours' => 12,
            'allows_observers' => true,
        ]));

        $slot = Slot::factory()->forcesParticipation(ParticipationMode::Lottery, 12, true)->create(
            $this->fittingSlotAttributes($event),
        );

        $proposal = ActivityProposal::factory()->create([
            'activity_id' => $activity->id,
            'event_id' => $event->id,
            'created_by' => $owner->id,
            'status' => ActivityProposalStatus::Pending,
        ]);

        app(ActivityProposalDecisionService::class)->accept($proposal, $slot->id);

        $activity->refresh();
        $proposal->refresh();
        $slot->refresh();

        $this->assertSame(ActivityProposalStatus::Accepted, $proposal->status);
        $this->assertSame($activity->id, (int) $slot->activity_id);
        $this->assertSame(ParticipationMode::Lottery, $activity->participation_mode);
        $this->assertSame(12, (int) $activity->lottery_draw_in_hours);
        $this->assertTrue($activity->allows_observers);
    }

    public function test_accept_succeeds_when_activity_already_matches_forced_slot(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->create(['created_by' => $owner->id]);
        $activity = Activity::factory()->create(array_merge($this->fittingActivityAttributes($owner), [
            'participation_mode' => ParticipationMode::HostApproval,
            'allows_observers' => false,
        ]));

        $slot = Slot::factory()->forcesParticipation(ParticipationMode::HostApproval)->create(
            $this->fittingSlotAttributes($event),
        );

        $proposal = ActivityProposal::factory()->create([
            'activity_id' => $activity->id,
            'event_id' => $event->id,
            'created_by' => $owner->id,
            'status' => ActivityProposalStatus::Pending,
        ]);

        app(ActivityProposalDecisionService::class)->accept($proposal, $slot->id);

        $this->assertSame(ActivityProposalStatus::Accepted, $proposal->fresh()->status);
    }
}
