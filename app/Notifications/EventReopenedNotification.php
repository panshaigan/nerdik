<?php

namespace App\Notifications;

use App\Models\Event;
use App\Models\User;
use App\Notifications\Concerns\BroadcastsWithDatabasePayload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Notification;

class EventReopenedNotification extends Notification implements ShouldQueue, ShouldQueueAfterCommit
{
    use BroadcastsWithDatabasePayload;
    use Queueable;

    public function __construct(
        public Event $event,
        public User $reopenedBy
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $reopenedByName = $this->reopenedBy->nickname ?? $this->reopenedBy->email ?? __('ui.common.unknown_user');

        $url = $this->event->slug !== ''
            ? route('events.show', ['event' => $this->event->slug], false)
            : route('search.index', [], false);

        return [
            'type' => 'event_reopened',
            'event_id' => $this->event->id,
            'event_name' => $this->event->name,
            'reopened_by_display' => $reopenedByName,
            'url' => $url,
            'toast_title' => __('ui.notifications.event_reopened_list'),
            'toast_description' => __('ui.notifications.event_reopened_toast', [
                'event' => $this->event->name,
                'by' => $reopenedByName,
            ]),
        ];
    }
}
