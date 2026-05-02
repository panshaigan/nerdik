<?php

namespace App\Livewire\Notifications;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class NotificationList extends Component
{
    use WithPagination;

    #[On('database-notifications-updated')]
    public function refreshNotificationList(): void
    {
        $this->resetPage();
    }

    public function markAllRead(): void
    {
        Auth::user()->unreadNotifications->markAsRead();
        session()->flash('status', __('All notifications marked as read.'));
    }

    public function markReadAndGo(string $id)
    {
        $notification = Auth::user()->notifications()->findOrFail($id);
        $notification->markAsRead();
        $url = $notification->data['url'] ?? '';
        $safeUrl = is_string($url) && Str::startsWith($url, '/') && ! Str::startsWith($url, '//')
            ? $url
            : route('dashboard');

        return redirect($safeUrl);
    }

    public function render()
    {
        $notifications = Auth::user()->notifications()->paginate(20);

        return view('livewire.notifications.notification-list', [
            'notifications' => $notifications,
        ]);
    }
}
