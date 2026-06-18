<?php

namespace App\Livewire\Notifications;

use App\Support\Notifications\NotificationListItemPresenter;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

class NotificationList extends Component
{
    use Toast;
    use WithPagination;

    #[Url(as: 'request')]
    public ?int $openRequestId = null;

    public function mount(): void
    {
        if ($this->openRequestId !== null) {
            $this->openRequestModal((string) $this->openRequestId);
        }
    }

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

        if ($isActionable) {
            $this->openRequestModal((string) $data['request_id']);
            $this->dispatch('database-notifications-updated', resetPagination: false);

            return;
        }

        $url = $data['url'] ?? '';
        $safeUrl = is_string($url) && Str::startsWith($url, '/') && ! Str::startsWith($url, '//')
            ? $url
            : route('dashboard');

        $this->redirect($safeUrl);
    }

    public function openRequestModal(string $requestId): void
    {
        $this->dispatch('open-user-request-modal', requestId: (int) $requestId);
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
