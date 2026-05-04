<?php

namespace Tests\Feature\Notifications;

use App\Models\Activity;
use App\Models\ActivityProposal;
use App\Models\ActivityUser;
use App\Models\ActivityWaitlistEntry;
use App\Models\User;
use App\Notifications\ActivityParticipantJoinedNotification;
use App\Notifications\ActivityParticipantLeftNotification;
use App\Notifications\ActivityRemovedByHostNotification;
use App\Notifications\ProposalAcceptedNotification;
use App\Notifications\ProposalRejectedNotification;
use App\Notifications\ProposalSubmittedNotification;
use App\Notifications\WaitlistPromotedNotification;
use App\Services\ActivityParticipantRosterService;
use App\Services\EventActivitySignupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AppNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_waitlist_promoted_notification_includes_expected_channels_when_enabled(): void
    {
        $user = User::factory()->create();
        $activity = Activity::factory()->create();

        $notification = new WaitlistPromotedNotification($activity);
        $channels = $notification->via($user);

        $this->assertContains('database', $channels);
        $this->assertContains('broadcast', $channels);
        $this->assertContains('mail', $channels);
    }

    public function test_waitlist_promoted_notification_skips_mail_when_email_disabled(): void
    {
        $user = User::factory()->create();
        $user->profile()->update([
            'notification_preferences' => [
                'waitlist_promoted' => [
                    'in_app' => true,
                    'email' => false,
                ],
            ],
        ]);
        $activity = Activity::factory()->create();

        $channels = (new WaitlistPromotedNotification($activity))->via($user);

        $this->assertContains('database', $channels);
        $this->assertNotContains('mail', $channels);
    }

    public function test_proposals_notification_returns_no_channels_when_both_prefs_disabled(): void
    {
        $user = User::factory()->create();
        $user->profile()->update([
            'notification_preferences' => [
                'proposals' => [
                    'in_app' => false,
                    'email' => false,
                ],
            ],
        ]);
        $proposal = ActivityProposal::factory()->create();

        $this->assertSame([], (new ProposalSubmittedNotification($proposal))->via($user));
        $this->assertSame([], (new ProposalAcceptedNotification($proposal))->via($user));
        $this->assertSame([], (new ProposalRejectedNotification($proposal))->via($user));
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

    public function test_user_join_activity_notifies_host_with_default_subject_when_below_minimum(): void
    {
        Notification::fake();

        $host = User::factory()->create();
        $joiner = User::factory()->create(['nickname' => 'Joiner']);
        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'updated_by' => $host->id,
            'min_participants' => 3,
            'max_participants' => 10,
        ]);

        app(EventActivitySignupService::class)->userJoinActivity($activity->fresh(), $joiner);

        Notification::assertSentTo(
            $host,
            ActivityParticipantJoinedNotification::class,
            function (ActivityParticipantJoinedNotification $notification) use ($activity, $joiner): bool {
                $payload = $notification->toArray($notification->activity);

                return $notification->activity->is($activity)
                    && $notification->joiner->is($joiner)
                    && $notification->participantCount === 1
                    && $payload['is_full'] === false
                    && $payload['min_reached'] === false
                    && str_contains($payload['toast_title'], $joiner->nickname);
            }
        );
    }

    public function test_user_join_activity_payload_marks_min_reached_when_count_at_minimum(): void
    {
        Notification::fake();

        $host = User::factory()->create();
        $existing = User::factory()->create();
        $joiner = User::factory()->create();
        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'updated_by' => $host->id,
            'min_participants' => 2,
            'max_participants' => 10,
        ]);

        ActivityUser::query()->create(['activity_id' => $activity->id, 'user_id' => $existing->id]);

        app(EventActivitySignupService::class)->userJoinActivity($activity->fresh(), $joiner);

        Notification::assertSentTo(
            $host,
            ActivityParticipantJoinedNotification::class,
            function (ActivityParticipantJoinedNotification $notification): bool {
                $payload = $notification->toArray($notification->activity);

                return $payload['min_reached'] === true
                    && $payload['is_full'] === false
                    && $payload['participant_count'] === 2;
            }
        );
    }

    public function test_user_join_activity_uses_roster_full_subject_when_count_reaches_max(): void
    {
        Notification::fake();

        $host = User::factory()->create();
        $existing = User::factory()->create();
        $joiner = User::factory()->create();
        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'updated_by' => $host->id,
            'min_participants' => 1,
            'max_participants' => 2,
        ]);

        ActivityUser::query()->create(['activity_id' => $activity->id, 'user_id' => $existing->id]);

        app(EventActivitySignupService::class)->userJoinActivity($activity->fresh(), $joiner);

        Notification::assertSentTo(
            $host,
            ActivityParticipantJoinedNotification::class,
            function (ActivityParticipantJoinedNotification $notification) use ($activity): bool {
                $payload = $notification->toArray($notification->activity);

                return $payload['is_full'] === true
                    && $payload['participant_count'] === 2
                    && str_contains($payload['toast_title'], $activity->name)
                    && str_contains($payload['toast_title'], 'full');
            }
        );
    }

    public function test_user_join_activity_does_not_notify_host_when_joiner_is_host(): void
    {
        Notification::fake();

        $host = User::factory()->create();
        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'updated_by' => $host->id,
            'min_participants' => 1,
            'max_participants' => 5,
        ]);

        app(EventActivitySignupService::class)->userJoinActivity($activity->fresh(), $host);

        Notification::assertNotSentTo($host, ActivityParticipantJoinedNotification::class);
    }

    public function test_user_leave_activity_notifies_host_with_replacement_when_waitlist_promotes(): void
    {
        Notification::fake();

        $host = User::factory()->create();
        $alice = User::factory()->create(['nickname' => 'Alice']);
        $bob = User::factory()->create(['nickname' => 'Bob']);
        $carol = User::factory()->create(['nickname' => 'Carol']);

        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'updated_by' => $host->id,
            'min_participants' => 2,
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

        Notification::assertSentTo(
            $host,
            ActivityParticipantLeftNotification::class,
            function (ActivityParticipantLeftNotification $notification) use ($bob, $carol): bool {
                $payload = $notification->toArray($notification->activity);

                return $notification->leaver->is($bob)
                    && $notification->promotedFromWaitlist?->is($carol) === true
                    && $payload['promoted_user_id'] === $carol->id
                    && $payload['below_minimum'] === false
                    && $payload['participant_count'] === 2;
            }
        );
    }

    public function test_user_leave_activity_notifies_host_with_below_minimum_when_no_replacement(): void
    {
        Notification::fake();

        $host = User::factory()->create();
        $alice = User::factory()->create(['nickname' => 'Alice']);
        $bob = User::factory()->create(['nickname' => 'Bob']);

        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'updated_by' => $host->id,
            'min_participants' => 2,
            'max_participants' => 5,
        ]);

        ActivityUser::query()->create(['activity_id' => $activity->id, 'user_id' => $alice->id]);
        $bobParticipant = ActivityUser::query()->create(['activity_id' => $activity->id, 'user_id' => $bob->id]);

        app(EventActivitySignupService::class)->userLeaveActivity($activity->fresh(), $bobParticipant);

        Notification::assertSentTo(
            $host,
            ActivityParticipantLeftNotification::class,
            function (ActivityParticipantLeftNotification $notification): bool {
                $payload = $notification->toArray($notification->activity);

                return $payload['promoted_user_id'] === null
                    && $payload['below_minimum'] === true
                    && $payload['participant_count'] === 1
                    && str_contains($payload['toast_title'], 'below minimum');
            }
        );
    }

    public function test_remove_participant_notifies_user_with_removed_mode(): void
    {
        Notification::fake();

        $host = User::factory()->create();
        $member = User::factory()->create();
        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'updated_by' => $host->id,
        ]);
        $participant = ActivityUser::query()->create([
            'activity_id' => $activity->id,
            'user_id' => $member->id,
        ]);

        app(ActivityParticipantRosterService::class)->removeParticipant($participant);

        Notification::assertSentTo(
            $member,
            ActivityRemovedByHostNotification::class,
            function (ActivityRemovedByHostNotification $notification) use ($activity): bool {
                return $notification->mode === ActivityRemovedByHostNotification::MODE_REMOVED
                    && $notification->activity->is($activity);
            }
        );
    }

    public function test_move_participant_to_waitlist_notifies_user_with_waitlist_mode(): void
    {
        Notification::fake();

        $host = User::factory()->create();
        $member = User::factory()->create();
        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'updated_by' => $host->id,
        ]);
        $participant = ActivityUser::query()->create([
            'activity_id' => $activity->id,
            'user_id' => $member->id,
        ]);

        app(ActivityParticipantRosterService::class)->moveParticipantToWaitlist($participant);

        Notification::assertSentTo(
            $member,
            ActivityRemovedByHostNotification::class,
            function (ActivityRemovedByHostNotification $notification) use ($activity): bool {
                return $notification->mode === ActivityRemovedByHostNotification::MODE_MOVED_TO_WAITLIST
                    && $notification->activity->is($activity);
            }
        );
    }

    public function test_new_roster_notifications_include_all_three_channels(): void
    {
        $user = User::factory()->create();
        $activity = Activity::factory()->create();

        $joined = new ActivityParticipantJoinedNotification($activity, $user, 1);
        $left = new ActivityParticipantLeftNotification($activity, $user, 0);
        $removed = new ActivityRemovedByHostNotification(
            $activity,
            ActivityRemovedByHostNotification::MODE_REMOVED,
        );

        foreach ([$joined, $left, $removed] as $notification) {
            $channels = $notification->via($user);

            $this->assertContains('database', $channels);
            $this->assertContains('broadcast', $channels);
            $this->assertContains('mail', $channels);
        }
    }
}
