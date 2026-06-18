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

        $this->assertSame(json_encode($payload, JSON_UNESCAPED_UNICODE), $display->title);
        $this->assertNull($display->subtitle);
        $this->assertSame('o-bell', $display->icon);
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
}
