<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationPreferenceKey;
use App\Models\Activity;
use App\Notifications\Concerns\BroadcastsWithDatabasePayload;
use App\Notifications\Concerns\RespectsNotificationPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ActivityPlacesLowNotification extends Notification implements ShouldQueue, ShouldQueueAfterCommit
{
    use BroadcastsWithDatabasePayload;
    use Queueable;
    use RespectsNotificationPreferences;

    public function __construct(
        public Activity $activity,
        public int $remaining,
        public int $max,
    ) {}

    protected function notificationPreferenceKey(): NotificationPreferenceKey
    {
        return NotificationPreferenceKey::ActivityPlacesLow;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('ui.notifications.activity_places_low_subject', [
                'activity' => $this->activity->name,
            ]))
            ->line(__('ui.notifications.activity_places_low_line_1', [
                'activity' => $this->activity->name,
            ]))
            ->line(__('ui.notifications.activity_places_low_line_2', [
                'remaining' => $this->remaining,
                'max' => $this->max,
            ]))
            ->action(
                __('ui.notifications.view_activity'),
                route('activities.show', $this->activity),
            );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'activity_places_low',
            'activity_id' => $this->activity->id,
            'activity_name' => $this->activity->name,
            'remaining' => $this->remaining,
            'max' => $this->max,
            'url' => route('activities.show', $this->activity, false),
            'toast_title' => __('ui.notifications.activity_places_low_list'),
            'toast_description' => __('ui.notifications.activity_places_low_toast', [
                'activity' => $this->activity->name,
                'remaining' => $this->remaining,
                'max' => $this->max,
            ]),
        ];
    }
}
