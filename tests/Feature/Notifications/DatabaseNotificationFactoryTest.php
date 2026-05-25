<?php

namespace Tests\Feature\Notifications;

use App\Livewire\Notifications\NotificationList;
use App\Models\Activity;
use App\Models\ActivityProposal;
use App\Models\User;
use App\Notifications\ProposalSubmittedNotification;
use Database\Factories\DatabaseNotificationFactory;
use Database\Factories\SampleNotificationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Livewire\Livewire;
use Tests\TestCase;

class DatabaseNotificationFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_from_notification_persists_matching_type_and_payload(): void
    {
        $user = User::factory()->create();
        $proposal = ActivityProposal::factory()->create()->load(['activity', 'event']);
        $notification = new ProposalSubmittedNotification($proposal);
        $expectedPayload = $notification->toArray($user);

        DatabaseNotificationFactory::new()
            ->for($user, 'notifiable')
            ->fromNotification($notification, $user)
            ->unread()
            ->create();

        $row = $user->notifications()->first();
        $this->assertInstanceOf(DatabaseNotification::class, $row);
        $this->assertSame(ProposalSubmittedNotification::class, $row->type);
        $this->assertSame('proposal_submitted', $row->data['type']);
        $this->assertSame($expectedPayload['activity_name'], $row->data['activity_name']);
        $this->assertSame($expectedPayload['event_name'], $row->data['event_name']);
        $this->assertSame($expectedPayload['url'], $row->data['url']);
        $this->assertNull($row->read_at);
    }

    public function test_notification_list_renders_factory_seeded_proposal_submitted(): void
    {
        $user = User::factory()->create();
        $proposal = ActivityProposal::factory()->create()->load(['activity', 'event']);

        DatabaseNotificationFactory::new()
            ->for($user, 'notifiable')
            ->fromNotification(new ProposalSubmittedNotification($proposal), $user)
            ->unread()
            ->create();

        Livewire::actingAs($user)
            ->test(NotificationList::class)
            ->assertSee($proposal->activity->name, false);
    }

    public function test_mark_all_read_marks_notifications_and_dispatches_without_session_status(): void
    {
        $user = User::factory()->create();
        $proposal = ActivityProposal::factory()->create()->load(['activity', 'event']);

        foreach (range(1, 3) as $ignored) {
            DatabaseNotificationFactory::new()
                ->for($user, 'notifiable')
                ->fromNotification(new ProposalSubmittedNotification($proposal), $user)
                ->unread()
                ->create();
        }

        $this->assertSame(3, $user->unreadNotifications()->count());

        Livewire::actingAs($user)
            ->test(NotificationList::class)
            ->call('markAllRead')
            ->assertDispatched('database-notifications-updated', resetPagination: false)
            ->assertSessionMissing('status');

        $this->assertSame(0, $user->fresh()->unreadNotifications()->count());
        $this->assertSame(3, $user->fresh()->notifications()->whereNotNull('read_at')->count());
    }

    public function test_random_sample_notification_uses_supported_ui_type(): void
    {
        $user = User::factory()->create();
        $proposal = ActivityProposal::factory()->create()->load(['activity', 'event']);
        $activity = Activity::factory()->create();
        $event = $proposal->event;

        $context = new SampleNotificationContext(
            proposals: collect([$proposal]),
            activities: collect([$activity]),
            events: collect([$event]),
            users: collect([$user]),
        );

        $supportedTypes = [
            'proposal_submitted',
            'proposal_accepted',
            'proposal_rejected',
            'waitlist_promoted',
            'activity_cancelled',
            'activity_reopened',
            'event_cancelled',
            'event_reopened',
        ];

        for ($i = 0; $i < 20; $i++) {
            DatabaseNotificationFactory::new()
                ->for($user, 'notifiable')
                ->randomSampleNotification($context, $user)
                ->create();
        }

        $types = $user->notifications()->pluck('data')->map(fn (array $data): string => $data['type'])->unique()->values()->all();

        foreach ($types as $type) {
            $this->assertContains($type, $supportedTypes, "Unexpected notification type: {$type}");
        }
    }
}
