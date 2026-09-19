<?php

namespace Tests\Feature\Notifications;

use App\Livewire\Notifications\NotificationDropdown;
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
        $proposal = ActivityProposal::factory()->create()->load(['activity', 'event']);

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
            ->assertSee($proposal->activity->name, false)
            ->assertSee(__('ui.notifications.view_all'), false)
            ->assertSee(route('notifications.index'), false)
            ->assertSeeHtml('data-ui="nav-notifications-badge"')
            ->call('handleNotificationClick', $visible->id)
            ->assertRedirect($visible->data['url']);
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
}
