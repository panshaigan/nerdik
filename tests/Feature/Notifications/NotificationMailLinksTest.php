<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Enums\UserRequestStatus;
use App\Models\Activity;
use App\Models\ActivityProposal;
use App\Models\Event;
use App\Models\User;
use App\Models\UserRequest;
use App\Notifications\ProposalRejectedNotification;
use App\Notifications\Scheduled\ScheduledPeriodicDigestNotification;
use App\Notifications\UserRequestResolvedNotification;
use App\Notifications\WaitlistPromotedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Markdown;
use Tests\TestCase;

class NotificationMailLinksTest extends TestCase
{
    use RefreshDatabase;

    public function test_mail_footer_links_to_profile_notifications_tab(): void
    {
        $user = User::factory()->create();
        $host = User::factory()->create();
        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'updated_by' => $host->id,
        ]);

        $mail = (new WaitlistPromotedNotification($activity))->toMail($user);
        $html = (string) app(Markdown::class)->render(
            $mail->markdown,
            $mail->data()
        );

        $this->assertStringContainsString('tab=notifications', $html);
        $this->assertStringNotContainsString('ui-profile-notifications-section', $html);
    }

    public function test_digest_mail_renders_clickable_links_not_raw_paths(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create(['name' => 'One More Game']);
        $relativeUrl = route('events.show', ['event' => $event], false);

        $notification = new ScheduledPeriodicDigestNotification([
            [
                'category' => 'interested_enrollment_window',
                'title' => 'Enrollment window for One More Game',
                'lines' => ['Starts soon'],
                'url' => $relativeUrl,
                'dedupe_key' => 'test:1',
            ],
        ], '2026-06-01');

        $mail = $notification->toMail($user);
        $html = (string) app(Markdown::class)->render(
            $mail->markdown,
            $mail->data()
        );

        $this->assertStringContainsString(url($relativeUrl), $html);
        $this->assertStringContainsString(__('ui.notifications.view_event'), $html);
        $this->assertStringNotContainsString('Open: '.$relativeUrl, $html);
    }

    public function test_proposal_rejected_mail_includes_view_activity_action(): void
    {
        $user = User::factory()->create();
        $host = User::factory()->create();
        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'updated_by' => $host->id,
            'name' => 'Chess Club',
        ]);
        $event = Event::factory()->create(['created_by' => $host->id]);
        $proposal = ActivityProposal::factory()->create([
            'activity_id' => $activity->id,
            'event_id' => $event->id,
            'status' => 'rejected',
        ]);
        $proposal->load(['activity', 'event']);

        $mail = (new ProposalRejectedNotification($proposal))->toMail($user);
        $html = (string) app(Markdown::class)->render(
            $mail->markdown,
            $mail->data()
        );

        $this->assertStringContainsString(
            route('activities.show', $activity),
            $html
        );
    }

    public function test_user_request_resolved_mail_includes_activity_action_for_activity_invite(): void
    {
        $requester = User::factory()->create();
        $recipient = User::factory()->create();
        $activity = Activity::factory()->create([
            'created_by' => $requester->id,
            'updated_by' => $requester->id,
        ]);

        $request = UserRequest::factory()->activityInvite()->create([
            'requester_id' => $requester->id,
            'recipient_id' => $recipient->id,
            'subject_type' => 'activity',
            'subject_id' => $activity->id,
            'status' => UserRequestStatus::Accepted,
        ]);

        $mail = (new UserRequestResolvedNotification($request))->toMail($requester);
        $html = (string) app(Markdown::class)->render(
            $mail->markdown,
            $mail->data()
        );

        $this->assertStringContainsString(
            route('activities.show', $activity),
            $html
        );
    }
}
