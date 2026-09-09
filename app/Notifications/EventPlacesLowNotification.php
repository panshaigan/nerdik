<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationPreferenceKey;
use App\Models\Event;
use App\Notifications\Concerns\BroadcastsWithDatabasePayload;
use App\Notifications\Concerns\RespectsNotificationPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EventPlacesLowNotification extends Notification implements ShouldQueue, ShouldQueueAfterCommit
{
    use BroadcastsWithDatabasePayload;
    use Queueable;
    use RespectsNotificationPreferences;

    public function __construct(
        public Event $event,
        public int $remaining,
        public int $max,
    ) {}

    protected function notificationPreferenceKey(): NotificationPreferenceKey
    {
        return NotificationPreferenceKey::EventPlacesLow;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('ui.notifications.event_places_low_subject', [
                'event' => $this->event->name,
            ]))
            ->line(__('ui.notifications.event_places_low_line_1', [
                'event' => $this->event->name,
            ]))
            ->line(__('ui.notifications.event_places_low_line_2', [
                'remaining' => $this->remaining,
                'max' => $this->max,
            ]))
            ->action(
                __('ui.notifications.view_event'),
                route('events.show', $this->event),
            );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'event_places_low',
            'event_id' => $this->event->id,
            'event_name' => $this->event->name,
            'remaining' => $this->remaining,
            'max' => $this->max,
            'url' => route('events.show', $this->event, false),
            'toast_title' => __('ui.notifications.event_places_low_list'),
            'toast_description' => __('ui.notifications.event_places_low_toast', [
                'event' => $this->event->name,
                'remaining' => $this->remaining,
                'max' => $this->max,
            ]),
        ];
    }
}
