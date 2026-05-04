<?php

namespace App\Notifications;

use App\Enums\NotificationPreferenceKey;
use App\Models\Activity;
use App\Models\Event;
use App\Models\User;
use App\Notifications\Concerns\BroadcastsWithDatabasePayload;
use App\Notifications\Concerns\RespectsNotificationPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ActivityReopenedNotification extends Notification implements ShouldQueue, ShouldQueueAfterCommit
{
    use BroadcastsWithDatabasePayload;
    use Queueable;
    use RespectsNotificationPreferences;

    public function __construct(
        public Activity $activity,
        public User $reopenedBy
    ) {}

    protected function notificationPreferenceKey(): NotificationPreferenceKey
    {
        return NotificationPreferenceKey::ActivityReopened;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $this->activity->loadMissing(['slot.event']);

        /** @var Event|null $event */
        $event = $this->activity->slot?->event;
        $reopenedByName = $this->reopenedBy->displayName();
        $message = (new MailMessage)
            ->subject(__('ui.notifications.activity_reopened_email_subject', ['activity' => $this->activity->name]))
            ->line(__('ui.notifications.activity_reopened_email_intro', [
                'activity' => $this->activity->name,
                'by' => $reopenedByName,
            ]));

        if ($event instanceof Event) {
            $message->line(__('ui.notifications.event_label', ['name' => $event->name]));
        }

        return $message->action(
            __('ui.notifications.view_activity'),
            route('activities.show', ['activity' => $this->activity, 'tab' => 'participation']),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $this->activity->loadMissing(['slot.event']);

        $event = $this->activity->slot?->event;
        $reopenedByName = $this->reopenedBy->displayName();

        return [
            'type' => 'activity_reopened',
            'activity_id' => $this->activity->id,
            'activity_name' => $this->activity->name,
            'event_id' => $event?->id,
            'event_name' => $event?->name,
            'reopened_by_display' => $reopenedByName,
            'url' => route('activities.show', ['activity' => $this->activity, 'tab' => 'participation'], false),
            'toast_title' => __('ui.notifications.activity_reopened_list'),
            'toast_description' => __('ui.notifications.activity_reopened_toast', [
                'activity' => $this->activity->name,
                'by' => $reopenedByName,
            ]),
        ];
    }
}
