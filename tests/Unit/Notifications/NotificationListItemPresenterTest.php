<?php

declare(strict_types=1);

namespace Tests\Unit\Notifications;

use App\Models\ActivityProposal;
use App\Models\User;
use App\Notifications\ProposalSubmittedNotification;
use App\Support\Notifications\NotificationListItemPresenter;
use Database\Factories\DatabaseNotificationFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class NotificationListItemPresenterTest extends TestCase
{
    use RefreshDatabase;

    private NotificationListItemPresenter $presenter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->presenter = new NotificationListItemPresenter;
    }

    #[Test]
    public function it_maps_proposal_submitted_with_activity_and_event_context(): void
    {
        $user = User::factory()->create();
        $notification = DatabaseNotificationFactory::new()
            ->for($user, 'notifiable')
            ->state([
                'data' => [
                    'type' => 'proposal_submitted',
                    'activity_name' => 'Dungeon Crawl',
                    'event_name' => 'Game Night',
                ],
            ])
            ->create();

        $display = $this->presenter->from($notification);

        $this->assertSame(__('ui.notifications.proposal_submitted_list'), $display->title);
        $this->assertSame('Dungeon Crawl · Game Night', $display->subtitle);
        $this->assertSame('o-inbox-arrow-down', $display->icon);
        $this->assertTrue($display->isUnread);
    }

    #[Test]
    public function it_maps_event_cancelled_using_event_name_only(): void
    {
        $user = User::factory()->create();
        $notification = DatabaseNotificationFactory::new()
            ->for($user, 'notifiable')
            ->read()
            ->state([
                'data' => [
                    'type' => 'event_cancelled',
                    'activity_name' => 'Should Not Appear',
                    'event_name' => 'Summer Con',
                ],
            ])
            ->create();

        $display = $this->presenter->from($notification);

        $this->assertSame(__('ui.notifications.event_cancelled_list'), $display->title);
        $this->assertSame('Summer Con', $display->subtitle);
        $this->assertFalse($display->isUnread);
    }

    #[Test]
    public function it_maps_scheduled_periodic_digest_with_item_titles(): void
    {
        $user = User::factory()->create();
        $notification = DatabaseNotificationFactory::new()
            ->for($user, 'notifiable')
            ->state([
                'data' => [
                    'type' => 'scheduled_periodic_digest',
                    'local_date' => '2026-06-16',
                    'items' => [
                        [
                            'category' => 'interested_enrollment_window',
                            'title' => 'Enrollment window for ConQuest',
                            'lines' => ['The next enrollment window starts at 2026-06-16 13:00 (in about 3 hour(s)).'],
                            'url' => '/events/conquest',
                            'dedupe_key' => 'interested_enrollment_window:5:2026-06-16T13:00:00Z',
                        ],
                    ],
                    'toast_title' => 'Scheduled reminders',
                    'toast_description' => '1 scheduled reminder item(s) are ready.',
                    'url' => '/notifications',
                ],
            ])
            ->create();

        $display = $this->presenter->from($notification);

        $this->assertSame('Scheduled reminders', $display->title);
        $this->assertSame('Enrollment window for ConQuest', $display->subtitle);
        $this->assertSame('o-clock', $display->icon);
        $this->assertTrue($display->isUnread);
    }

    #[Test]
    public function it_maps_scheduled_periodic_digest_with_multiple_item_titles(): void
    {
        $user = User::factory()->create();
        $notification = DatabaseNotificationFactory::new()
            ->for($user, 'notifiable')
            ->state([
                'data' => [
                    'type' => 'scheduled_periodic_digest',
                    'items' => [
                        ['title' => 'Enrollment window for ConQuest'],
                        ['title' => 'Cancellation deadline approaching: Dungeon Crawl'],
                    ],
                    'toast_title' => 'Scheduled reminders',
                ],
            ])
            ->create();

        $display = $this->presenter->from($notification);

        $this->assertSame(
            'Enrollment window for ConQuest · Cancellation deadline approaching: Dungeon Crawl',
            $display->subtitle,
        );
    }

    #[Test]
    public function it_falls_back_for_unknown_types(): void
    {
        $user = User::factory()->create();
        $payload = ['type' => 'mystery_ping', 'foo' => 'bar'];
        $notification = DatabaseNotificationFactory::new()
            ->for($user, 'notifiable')
            ->state(['data' => $payload])
            ->create();

        $display = $this->presenter->from($notification);

        $this->assertSame(__('Notification'), $display->title);
        $this->assertNull($display->subtitle);
        $this->assertSame('o-bell', $display->icon);
        $this->assertStringNotContainsString('{', $display->title);
    }

    #[Test]
    public function it_falls_back_to_toast_title_for_unknown_types_with_toast_metadata(): void
    {
        $user = User::factory()->create();
        $notification = DatabaseNotificationFactory::new()
            ->for($user, 'notifiable')
            ->state([
                'data' => [
                    'type' => 'future_notification_type',
                    'toast_title' => 'Something happened',
                    'toast_description' => 'More context here',
                ],
            ])
            ->create();

        $display = $this->presenter->from($notification);

        $this->assertSame('Something happened', $display->title);
        $this->assertSame('More context here', $display->subtitle);
        $this->assertStringNotContainsString('{', $display->title);
    }

    #[Test]
    public function it_maps_activity_participant_left_with_replacement(): void
    {
        $user = User::factory()->create();
        $notification = DatabaseNotificationFactory::new()
            ->for($user, 'notifiable')
            ->state([
                'data' => [
                    'type' => 'activity_participant_left',
                    'activity_id' => 263,
                    'activity_name' => 'loteria',
                    'leaver_id' => 2,
                    'leaver_display' => 'bob',
                    'promoted_user_id' => 412,
                    'promoted_display' => 'kurpievski',
                    'participant_count' => 2,
                    'min_participants' => 1,
                    'max_participants' => 3,
                    'below_minimum' => false,
                    'url' => '/activities/loteria?tab=participation',
                    'toast_title' => 'bob left loteria, kurpievski took the spot',
                    'toast_description' => 'Activity: loteria',
                ],
            ])
            ->create();

        $display = $this->presenter->from($notification);

        $this->assertSame('bob left loteria, kurpievski took the spot', $display->title);
        $this->assertSame('Activity: loteria', $display->subtitle);
        $this->assertSame('o-user-minus', $display->icon);
        $this->assertStringNotContainsString('{', $display->title);
    }

    #[Test]
    public function it_maps_activity_participant_joined(): void
    {
        $user = User::factory()->create();
        $notification = DatabaseNotificationFactory::new()
            ->for($user, 'notifiable')
            ->state([
                'data' => [
                    'type' => 'activity_participant_joined',
                    'activity_name' => 'Board Games',
                    'toast_title' => 'Alice joined Board Games',
                    'toast_description' => 'Activity: Board Games',
                ],
            ])
            ->create();

        $display = $this->presenter->from($notification);

        $this->assertSame('Alice joined Board Games', $display->title);
        $this->assertSame('Activity: Board Games', $display->subtitle);
        $this->assertSame('o-user-plus', $display->icon);
    }

    #[Test]
    public function it_maps_activity_removed_by_host(): void
    {
        $user = User::factory()->create();
        $notification = DatabaseNotificationFactory::new()
            ->for($user, 'notifiable')
            ->state([
                'data' => [
                    'type' => 'activity_removed_by_host',
                    'mode' => 'removed',
                    'activity_name' => 'Chess Club',
                    'toast_title' => 'You were removed from Chess Club',
                    'toast_description' => 'Activity: Chess Club',
                ],
            ])
            ->create();

        $display = $this->presenter->from($notification);

        $this->assertSame('You were removed from Chess Club', $display->title);
        $this->assertSame('Activity: Chess Club', $display->subtitle);
        $this->assertSame('o-user-minus', $display->icon);
    }

    #[Test]
    public function it_builds_display_from_real_notification_payload(): void
    {
        $user = User::factory()->create();
        $proposal = ActivityProposal::factory()->create()->load(['activity', 'event']);
        $notification = DatabaseNotificationFactory::new()
            ->for($user, 'notifiable')
            ->fromNotification(new ProposalSubmittedNotification($proposal), $user)
            ->create();

        $display = $this->presenter->from($notification);

        $this->assertSame($proposal->activity->name.' · '.$proposal->event->name, $display->subtitle);
        $this->assertNotSame('', $display->timeAgo);
    }

    #[Test]
    public function it_maps_user_request_received_notification(): void
    {
        $user = User::factory()->create();
        $notification = DatabaseNotificationFactory::new()
            ->for($user, 'notifiable')
            ->state([
                'data' => [
                    'type' => 'user_request_received',
                    'request_type' => 'activity_invite',
                    'requester_name' => 'Host User',
                    'subject_label' => 'Friday RPG',
                ],
            ])
            ->create();

        $display = $this->presenter->from($notification);

        $this->assertSame(__('ui.user_requests.received_activity_invite'), $display->title);
        $this->assertSame('Host User · Friday RPG', $display->subtitle);
    }

    #[Test]
    public function it_maps_user_request_resolved_notification_with_responder_and_type(): void
    {
        $user = User::factory()->create();
        $notification = DatabaseNotificationFactory::new()
            ->for($user, 'notifiable')
            ->state([
                'data' => [
                    'type' => 'user_request_resolved',
                    'request_type' => 'organization_invite',
                    'request_status' => 'accepted',
                    'responder_name' => 'Recipient User',
                    'subject_label' => 'Acme Guild',
                ],
            ])
            ->create();

        $display = $this->presenter->from($notification);

        $this->assertSame(__('ui.user_requests.resolved_accepted'), $display->title);
        $this->assertSame(
            'Recipient User · '.__('ui.user_requests.received_organization_invite').' · Acme Guild',
            $display->subtitle,
        );
    }
}
