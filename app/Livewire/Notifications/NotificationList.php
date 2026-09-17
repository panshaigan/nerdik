<?php

namespace App\Livewire\Notifications;

use App\Support\Notifications\NotificationListItemPresenter;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

class NotificationList extends Component
{
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

    public function handleNotificationClick(string $id): void
    {
        $notification = Auth::user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        $data = $notification->data;
        $isActionable = ($data['actionable'] ?? false) === true
            && isset($data['request_id']);

        // Do not dispatch database-notifications-updated before redirect: that
        // races a second Livewire update against navigation and aborts mid-flight
        // (Sentry UnhandledRejection with Livewire's {status,body,json,errors}).
        // The destination page loads fresh unread indicators.

        if ($isActionable) {
            $this->redirect(
                route('requests.index', ['request' => $data['request_id']]),
                navigate: true,
            );

            return;
        }

        if (($data['type'] ?? '') === 'scheduled_periodic_digest') {
            $items = $data['items'] ?? [];
            if (count($items) === 1) {
                $itemUrl = $items[0]['url'] ?? '';
                if (is_string($itemUrl) && Str::startsWith($itemUrl, '/') && ! Str::startsWith($itemUrl, '//')) {
                    $this->redirect($itemUrl, navigate: true);

                    return;
                }
            }
        }

        $url = $data['url'] ?? '';
        $safeUrl = is_string($url) && Str::startsWith($url, '/') && ! Str::startsWith($url, '//')
            ? $url
            : route('dashboard');

        $this->redirect($safeUrl, navigate: true);
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
