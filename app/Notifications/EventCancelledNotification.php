<?php

namespace App\Notifications;

use App\Enums\NotificationPreferenceKey;
use App\Notifications\Concerns\BroadcastsWithDatabasePayload;
use App\Notifications\Concerns\RespectsNotificationPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EventCancelledNotification extends Notification implements ShouldQueue, ShouldQueueAfterCommit
{
    use BroadcastsWithDatabasePayload;
    use Queueable;
    use RespectsNotificationPreferences;

    public function __construct(
        public int $eventId,
        public string $eventName,
        public string $eventSlug = ''
    ) {}

    protected function notificationPreferenceKey(): NotificationPreferenceKey
    {
        return NotificationPreferenceKey::EventCancelled;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject(__('ui.notifications.event_cancelled_email_subject', ['event' => $this->eventName]))
            ->line(__('ui.notifications.event_cancelled_email_intro', ['event' => $this->eventName]));

        return $message->action(__('ui.notifications.view_event'), $this->resolveEventShowUrl(true));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $url = $this->resolveEventShowUrl(false);

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

    private function resolveEventShowUrl(bool $absolute): string
    {
        if ($this->eventSlug !== '') {
            return route('events.show', ['event' => $this->eventSlug], $absolute);
        }

        return route('search.index', [], $absolute);
    }
}
