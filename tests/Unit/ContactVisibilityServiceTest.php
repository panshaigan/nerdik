<?php

namespace Tests\Unit;

use App\Enums\ActivityProposalStatus;
use App\Models\Activity;
use App\Models\ActivityProposal;
use App\Models\ActivityUser;
use App\Models\Event;
use App\Models\User;
use App\Services\ContactVisibilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ContactVisibilityServiceTest extends TestCase
{
    use RefreshDatabase;

    private ContactVisibilityService $service;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(ContactVisibilityService::class);
    }

    #[Test]
    public function viewer_can_see_own_contact_info(): void
    {
        $user = User::factory()->create();

        $this->assertTrue($this->service->canViewContactInfo($user, $user));
    }

    #[Test]
    public function host_can_see_participant_contact_when_participant_joined_hosts_activity(): void
    {
        $host = User::factory()->create();
        $participant = User::factory()->create();
        $activity = Activity::factory()->create(['created_by' => $host->id]);
        ActivityUser::query()->create([
            'activity_id' => $activity->id,
            'user_id' => $participant->id,
            'is_absent' => false,
            'deleted_at' => null,
        ]);

        $this->assertTrue($this->service->canViewContactInfo($host, $participant));
    }

    #[Test]
    public function participant_can_see_host_contact_when_participating_in_hosts_activity(): void
    {
        $host = User::factory()->create();
        $participant = User::factory()->create();
        $activity = Activity::factory()->create(['created_by' => $host->id]);
        ActivityUser::query()->create([
            'activity_id' => $activity->id,
            'user_id' => $participant->id,
            'is_absent' => false,
            'deleted_at' => null,
        ]);

        $this->assertTrue($this->service->canViewContactInfo($participant, $host));
    }

    #[Test]
    public function event_host_can_see_proposer_contact_when_target_proposed_to_viewers_event(): void
    {
        $eventHost = User::factory()->create();
        $proposer = User::factory()->create();
        $event = Event::factory()->create(['created_by' => $eventHost->id]);
        ActivityProposal::factory()->create([
            'event_id' => $event->id,
            'created_by' => $proposer->id,
            'status' => ActivityProposalStatus::Pending,
        ]);

        $this->assertTrue($this->service->canViewContactInfo($eventHost, $proposer));
    }

    #[Test]
    public function proposer_can_see_event_host_contact_when_viewer_proposed_to_targets_event(): void
    {
        $eventHost = User::factory()->create();
        $proposer = User::factory()->create();
        $event = Event::factory()->create(['created_by' => $eventHost->id]);
        ActivityProposal::factory()->create([
            'event_id' => $event->id,
            'created_by' => $proposer->id,
            'status' => ActivityProposalStatus::Pending,
        ]);

        $this->assertTrue($this->service->canViewContactInfo($proposer, $eventHost));
    }

    #[Test]
    public function rejected_proposal_still_grants_contact_access(): void
    {
        $eventHost = User::factory()->create();
        $proposer = User::factory()->create();
        $event = Event::factory()->create(['created_by' => $eventHost->id]);
        ActivityProposal::factory()->create([
            'event_id' => $event->id,
            'created_by' => $proposer->id,
            'status' => ActivityProposalStatus::Rejected,
        ]);

        $this->assertTrue($this->service->canViewContactInfo($eventHost, $proposer));
        $this->assertTrue($this->service->canViewContactInfo($proposer, $eventHost));
    }

    #[Test]
    public function stranger_cannot_see_contact_info(): void
    {
        $host = User::factory()->create();
        $participant = User::factory()->create();
        $stranger = User::factory()->create();
        $activity = Activity::factory()->create(['created_by' => $host->id]);
        ActivityUser::query()->create([
            'activity_id' => $activity->id,
            'user_id' => $participant->id,
            'is_absent' => false,
            'deleted_at' => null,
        ]);

        $this->assertFalse($this->service->canViewContactInfo($stranger, $participant));
        $this->assertFalse($this->service->canViewContactInfo($stranger, $host));
    }

    #[Test]
    public function co_participants_without_host_or_proposal_link_cannot_see_each_others_contact(): void
    {
        $host = User::factory()->create();
        $participantA = User::factory()->create();
        $participantB = User::factory()->create();
        $activity = Activity::factory()->create(['created_by' => $host->id]);
        ActivityUser::query()->create([
            'activity_id' => $activity->id,
            'user_id' => $participantA->id,
            'is_absent' => false,
            'deleted_at' => null,
        ]);
        ActivityUser::query()->create([
            'activity_id' => $activity->id,
            'user_id' => $participantB->id,
            'is_absent' => false,
            'deleted_at' => null,
        ]);

        $this->assertFalse($this->service->canViewContactInfo($participantA, $participantB));
        $this->assertFalse($this->service->canViewContactInfo($participantB, $participantA));
    }

    #[Test]
    public function soft_deleted_participation_does_not_grant_contact_access(): void
    {
        $host = User::factory()->create();
        $participant = User::factory()->create();
        $activity = Activity::factory()->create(['created_by' => $host->id]);
        ActivityUser::query()->create([
            'activity_id' => $activity->id,
            'user_id' => $participant->id,
            'is_absent' => false,
            'deleted_at' => now(),
        ]);

        $this->assertFalse($this->service->canViewContactInfo($host, $participant));
        $this->assertFalse($this->service->canViewContactInfo($participant, $host));
    }

    #[Test]
    public function soft_deleted_proposal_does_not_grant_contact_access(): void
    {
        $eventHost = User::factory()->create();
        $proposer = User::factory()->create();
        $event = Event::factory()->create(['created_by' => $eventHost->id]);
        $proposal = ActivityProposal::factory()->create([
            'event_id' => $event->id,
            'created_by' => $proposer->id,
            'status' => ActivityProposalStatus::Pending,
        ]);
        $proposal->delete();

        $this->assertFalse($this->service->canViewContactInfo($eventHost, $proposer));
        $this->assertFalse($this->service->canViewContactInfo($proposer, $eventHost));
    }

    #[Test]
    public function relationship_on_any_activity_grants_contact_without_page_context(): void
    {
        $host = User::factory()->create();
        $participant = User::factory()->create();
        $relatedActivity = Activity::factory()->create(['created_by' => $host->id]);
        Activity::factory()->create(['created_by' => $host->id]);
        ActivityUser::query()->create([
            'activity_id' => $relatedActivity->id,
            'user_id' => $participant->id,
            'is_absent' => false,
            'deleted_at' => null,
        ]);

        $this->assertTrue($this->service->canViewContactInfo($host, $participant));
    }
}
