<?php

namespace Tests\Feature\Notifications;

use App\Models\Activity;
use App\Models\ActivityProposal;
use App\Models\ActivityUser;
use App\Models\ActivityWaitlistEntry;
use App\Models\User;
use App\Notifications\ProposalAcceptedNotification;
use App\Notifications\ProposalRejectedNotification;
use App\Notifications\ProposalSubmittedNotification;
use App\Notifications\WaitlistPromotedNotification;
use App\Services\EventActivitySignupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AppNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_waitlist_promoted_notification_includes_broadcast_channel(): void
    {
        $user = User::factory()->create();
        $activity = Activity::factory()->create();

        $notification = new WaitlistPromotedNotification($activity);
        $channels = $notification->via($user);

        $this->assertContains('database', $channels);
        $this->assertContains('broadcast', $channels);
        if ($user->notify_email_waitlist_promoted ?? true) {
            $this->assertContains('mail', $channels);
        }
    }

    public function test_proposal_notifications_include_broadcast_channel(): void
    {
        $user = User::factory()->create();
        $proposal = ActivityProposal::factory()->create();

        $this->assertContains('broadcast', (new ProposalSubmittedNotification($proposal))->via($user));
        $this->assertContains('broadcast', (new ProposalAcceptedNotification($proposal))->via($user));
        $this->assertContains('broadcast', (new ProposalRejectedNotification($proposal))->via($user));
    }

    public function test_proposal_submitted_notification_payload_includes_event_id_for_live_refresh(): void
    {
        $user = User::factory()->create();
        $proposal = ActivityProposal::factory()->create()->load(['activity', 'event']);

        $payload = (new ProposalSubmittedNotification($proposal))->toArray($user);

        $this->assertArrayHasKey('event_id', $payload);
        $this->assertSame((int) $proposal->event_id, (int) $payload['event_id']);
        $this->assertSame(
            ProposalSubmittedNotification::LIVEWIRE_REFRESH_PROPOSAL_SUBMITTED_FOR_EVENT,
            $payload['lw_event_refresh'] ?? null
        );
    }

    public function test_notification_payload_urls_target_expected_tabs(): void
    {
        $user = User::factory()->create();
        $proposal = ActivityProposal::factory()->create()->load(['activity', 'event']);
        $activity = Activity::factory()->create();

        $acceptedPayload = (new ProposalAcceptedNotification($proposal))->toArray($user);
        $rejectedPayload = (new ProposalRejectedNotification($proposal))->toArray($user);
        $submittedPayload = (new ProposalSubmittedNotification($proposal))->toArray($user);
        $waitlistPayload = (new WaitlistPromotedNotification($activity))->toArray($user);

        $this->assertStringContainsString('tab=plan', $acceptedPayload['url']);
        $this->assertStringContainsString('/events/', $acceptedPayload['url']);

        $this->assertStringContainsString('/activities/', $rejectedPayload['url']);
        $this->assertStringNotContainsString('tab=', $rejectedPayload['url']);

        $this->assertStringContainsString('tab=proposals', $submittedPayload['url']);
        $this->assertStringContainsString('/events/', $submittedPayload['url']);

        $this->assertStringContainsString('tab=participation', $waitlistPayload['url']);
        $this->assertStringContainsString('/activities/', $waitlistPayload['url']);
    }

    public function test_notification_payloads_include_toast_metadata(): void
    {
        $user = User::factory()->create();
        $proposal = ActivityProposal::factory()->create()->load(['activity', 'event']);
        $activity = Activity::factory()->create();

        $payloads = [
            (new ProposalAcceptedNotification($proposal))->toArray($user),
            (new ProposalRejectedNotification($proposal))->toArray($user),
            (new ProposalSubmittedNotification($proposal))->toArray($user),
            (new WaitlistPromotedNotification($activity))->toArray($user),
        ];

        foreach ($payloads as $payload) {
            $this->assertArrayHasKey('toast_title', $payload);
            $this->assertArrayHasKey('toast_description', $payload);
            $this->assertIsString($payload['toast_title']);
            $this->assertIsString($payload['toast_description']);
            $this->assertNotSame('', $payload['toast_title']);
            $this->assertNotSame('', $payload['toast_description']);
        }
    }

    public function test_user_leave_activity_notifies_promoted_waitlist_user(): void
    {
        Notification::fake();

        $host = User::factory()->create();
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $carol = User::factory()->create();

        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'updated_by' => $host->id,
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'max_participants' => 2,
        ]);

        ActivityUser::query()->create(['activity_id' => $activity->id, 'user_id' => $alice->id]);
        $bobParticipant = ActivityUser::query()->create(['activity_id' => $activity->id, 'user_id' => $bob->id]);
        ActivityWaitlistEntry::query()->create([
            'activity_id' => $activity->id,
            'user_id' => $carol->id,
            'position' => 1,
        ]);

        app(EventActivitySignupService::class)->userLeaveActivity($activity->fresh(), $bobParticipant);

        Notification::assertSentTo($carol, WaitlistPromotedNotification::class);
        Notification::assertNotSentTo($bob, WaitlistPromotedNotification::class);
    }
}
