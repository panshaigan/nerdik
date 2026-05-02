<?php

namespace App\Notifications;

use App\Notifications\Concerns\BroadcastsWithDatabasePayload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Notification;

class EventCancelledNotification extends Notification implements ShouldQueue, ShouldQueueAfterCommit
{
    use BroadcastsWithDatabasePayload;
    use Queueable;

    public function __construct(
        public int $eventId,
        public string $eventName,
        public string $eventSlug = ''
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
        $url = $this->eventSlug !== ''
            ? route('events.show', ['event' => $this->eventSlug], false)
            : route('search.index', [], false);

        return [
            'type' => 'event_cancelled',
            'event_id' => $this->eventId,
            'event_name' => $this->eventName,
            'url' => $url,
            'toast_title' => __('ui.notifications.event_cancelled_list'),
            'toast_description' => __('ui.notifications.event_cancelled_toast', [
                'event' => $this->eventName,
            ]),
        ];
    }
}
