<?php

namespace App\Livewire\Notifications;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class NotificationList extends Component
{
    use WithPagination;

    public function markAllRead(): void
    {
        Auth::user()->unreadNotifications->markAsRead();
        session()->flash('status', __('All notifications marked as read.'));
    }

    public function markReadAndGo(string $id)
    {
        $notification = Auth::user()->notifications()->findOrFail($id);
        $notification->markAsRead();
        $url = $notification->data['url'] ?? route('dashboard');

        return redirect($url);
    }

    public function render()
    {
        $notifications = Auth::user()->notifications()->paginate(20);

        return view('livewire.notifications.notification-list', [
            'notifications' => $notifications,
        ]);
    }
}
