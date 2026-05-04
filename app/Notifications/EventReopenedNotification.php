<?php

namespace App\Notifications;

use App\Enums\NotificationPreferenceKey;
use App\Models\Event;
use App\Models\User;
use App\Notifications\Concerns\BroadcastsWithDatabasePayload;
use App\Notifications\Concerns\RespectsNotificationPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EventReopenedNotification extends Notification implements ShouldQueue, ShouldQueueAfterCommit
{
    use BroadcastsWithDatabasePayload;
    use Queueable;
    use RespectsNotificationPreferences;

    public function __construct(
        public Event $event,
        public User $reopenedBy
    ) {}

    protected function notificationPreferenceKey(): NotificationPreferenceKey
    {
        return NotificationPreferenceKey::EventReopened;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $reopenedByName = $this->reopenedBy->displayName();

        return (new MailMessage)
            ->subject(__('ui.notifications.event_reopened_email_subject', ['event' => $this->event->name]))
            ->line(__('ui.notifications.event_reopened_email_intro', [
                'event' => $this->event->name,
                'by' => $reopenedByName,
            ]))
            ->action(__('ui.notifications.view_event'), $this->resolveEventShowUrl(true));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $reopenedByName = $this->reopenedBy->displayName();

        $url = $this->resolveEventShowUrl(false);

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

    private function resolveEventShowUrl(bool $absolute): string
    {
        if ($this->event->slug !== '') {
            return route('events.show', ['event' => $this->event->slug], $absolute);
        }

        return route('search.index', [], $absolute);
    }
}
