<?php

namespace App\Livewire\Notifications;

use App\Livewire\Concerns\HandlesDatabaseNotificationClick;
use App\Support\Notifications\NotificationListItemPresenter;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

class NotificationList extends Component
{
    use HandlesDatabaseNotificationClick;
    use Toast;
    use WithPagination;

    #[On('database-notifications-updated')]
    public function refreshNotificationList(bool $resetPagination = true): void
    {
        if ($resetPagination) {
            $this->resetPage();
        }
    }

    public function markAllRead(): void
    {
        Auth::user()->unreadNotifications()->update(['read_at' => now()]);

        $this->success(__('All notifications marked as read.'));

        $this->dispatch('database-notifications-updated', resetPagination: false);
    }

    public function render(NotificationListItemPresenter $presenter)
    {
        $notifications = Auth::user()->notifications()->paginate(20);

        $displays = [];
        foreach ($notifications as $notification) {
            $displays[$notification->id] = $presenter->from($notification);
        }

        return view('livewire.notifications.notification-list', [
            'notifications' => $notifications,
            'displays' => $displays,
        ]);
    }
}
