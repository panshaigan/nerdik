<?php

namespace App\Livewire\Notifications;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Redirector;
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

    public function markReadAndGo(string $id): Redirector|RedirectResponse
    {
        $notification = Auth::user()->notifications()->findOrFail($id);
        $notification->markAsRead();
        $url = $notification->data['url'] ?? route('dashboard');

        return redirect($url);
    }

    public function render(): View
    {
        $notifications = Auth::user()->notifications()->paginate(20);

        return view('livewire.notifications.notification-list', [
            'notifications' => $notifications,
        ]);
    }
}
