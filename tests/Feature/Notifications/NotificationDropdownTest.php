<?php

namespace Tests\Feature\Notifications;

use App\Livewire\Notifications\NotificationDropdown;
use App\Models\Activity;
use App\Models\ActivityProposal;
use App\Models\User;
use App\Notifications\ProposalSubmittedNotification;
use Database\Factories\DatabaseNotificationFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class NotificationDropdownTest extends TestCase
{
    use RefreshDatabase;

    public function test_dropdown_lists_latest_notifications_and_link_to_inbox(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $activity = Activity::factory()->create(['name' => "Prof. Kolby O'Kon"]);
        $proposal = ActivityProposal::factory()
            ->for($activity)
            ->create()
            ->load(['activity', 'event']);

        DatabaseNotificationFactory::new()
            ->for($other, 'notifiable')
            ->fromNotification(new ProposalSubmittedNotification($proposal), $other)
            ->unread()
            ->create(['created_at' => now()->subMinute()]);

        $visible = DatabaseNotificationFactory::new()
            ->for($user, 'notifiable')
            ->fromNotification(new ProposalSubmittedNotification($proposal), $user)
            ->unread()
            ->create(['created_at' => now()]);

        Livewire::actingAs($user)
            ->test(NotificationDropdown::class)
            ->assertSee($proposal->activity->name)
            ->assertSeeHtml('data-ui="nav-notifications-clear-all"')
            ->assertSee(__('ui.notifications.view_all_short'), false)
            ->assertSee(route('notifications.index'), false)
            ->assertSeeHtml('data-ui="nav-notifications-badge"')
            ->call('handleNotificationClick', $visible->id)
            ->assertRedirect($visible->data['url']);

        $this->assertNotNull($visible->fresh()->read_at);
        $this->assertSame(0, $user->fresh()->unreadNotifications()->count());
    }

    public function test_dropdown_only_shows_the_latest_preview_limit(): void
    {
        $user = User::factory()->create();
        $proposal = ActivityProposal::factory()->create()->load(['activity', 'event']);

        $oldest = DatabaseNotificationFactory::new()
            ->for($user, 'notifiable')
            ->fromNotification(new ProposalSubmittedNotification($proposal), $user)
            ->read()
            ->create(['created_at' => now()->subDays(10)]);

        foreach (range(1, NotificationDropdown::PREVIEW_LIMIT) as $offset) {
            DatabaseNotificationFactory::new()
                ->for($user, 'notifiable')
                ->fromNotification(new ProposalSubmittedNotification($proposal), $user)
                ->unread()
                ->create(['created_at' => now()->subDays($offset)]);
        }

        Livewire::actingAs($user)
            ->test(NotificationDropdown::class)
            ->assertViewHas('notifications', function ($notifications) use ($oldest): bool {
                return $notifications->count() === NotificationDropdown::PREVIEW_LIMIT
                    && $notifications->doesntContain('id', $oldest->id);
            });
    }

    public function test_dropdown_clear_all_marks_unread_notifications_and_updates_badge(): void
    {
        $user = User::factory()->create();
        $proposal = ActivityProposal::factory()->create()->load(['activity', 'event']);

        foreach (range(1, 3) as $offset) {
            DatabaseNotificationFactory::new()
                ->for($user, 'notifiable')
                ->fromNotification(new ProposalSubmittedNotification($proposal), $user)
                ->unread()
                ->create(['created_at' => now()->subMinutes($offset)]);
        }

        Livewire::actingAs($user)
            ->test(NotificationDropdown::class)
            ->assertSeeHtml('data-ui="nav-notifications-badge"')
            ->assertSee(__('ui.notifications.clear_all'), false)
            ->call('markAllRead')
            ->assertDispatched('database-notifications-updated', resetPagination: false)
            ->assertDontSeeHtml('data-ui="nav-notifications-badge"');

        $this->assertSame(0, $user->fresh()->unreadNotifications()->count());
        $this->assertSame(3, $user->fresh()->notifications()->whereNotNull('read_at')->count());
    }

    public function test_dropdown_always_shows_clear_all_footer_even_when_nothing_unread(): void
    {
        $user = User::factory()->create();
        $proposal = ActivityProposal::factory()->create()->load(['activity', 'event']);

        DatabaseNotificationFactory::new()
            ->for($user, 'notifiable')
            ->fromNotification(new ProposalSubmittedNotification($proposal), $user)
            ->read()
            ->create();

        Livewire::actingAs($user)
            ->test(NotificationDropdown::class)
            ->assertSeeHtml('data-ui="nav-notifications-footer"')
            ->assertSeeHtml('data-ui="nav-notifications-clear-all"')
            ->assertSee(__('ui.notifications.clear_all'), false)
            ->assertSee(__('ui.notifications.view_all_short'), false)
            ->assertDontSeeHtml('data-ui="nav-notifications-badge"');
    }
}
