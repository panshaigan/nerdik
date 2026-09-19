<?php

namespace App\Livewire\Notifications;

use App\Livewire\Concerns\HandlesDatabaseNotificationClick;
use App\Support\Notifications\NotificationListItemPresenter;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

class NotificationDropdown extends Component
{
    use HandlesDatabaseNotificationClick;

    public const PREVIEW_LIMIT = 8;

    public string $variant = 'desktop';

    #[On('database-notifications-updated')]
    public function refreshNotificationDropdown(): void
    {
        //
    }

    public function render(NotificationListItemPresenter $presenter)
    {
        $user = Auth::user();
        $notifications = $user->notifications()->latest()->limit(self::PREVIEW_LIMIT)->get();
        $unreadCount = $user->unreadNotifications()->count();

        $displays = [];
        foreach ($notifications as $notification) {
            $displays[$notification->id] = $presenter->from($notification);
        }

        return view('livewire.notifications.notification-dropdown', [
            'notifications' => $notifications,
            'displays' => $displays,
            'unreadCount' => $unreadCount,
            'unreadBadge' => $unreadCount > 9 ? '9+' : (string) $unreadCount,
        ]);
    }
}
