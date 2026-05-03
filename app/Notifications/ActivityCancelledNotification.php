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

class ActivityCancelledNotification extends Notification implements ShouldQueue, ShouldQueueAfterCommit
{
    use BroadcastsWithDatabasePayload;
    use Queueable;
    use RespectsNotificationPreferences;

    public function __construct(
        public Activity $activity,
        public User $cancelledBy
    ) {}

    protected function notificationPreferenceKey(): NotificationPreferenceKey
    {
        return NotificationPreferenceKey::ActivityCancelled;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $this->activity->loadMissing(['slot.event']);

        $event = $this->activity->slot?->event;
        $cancelledByName = $this->cancelledBy->nickname ?? $this->cancelledBy->email ?? __('ui.common.unknown_user');
        $message = (new MailMessage)
            ->subject(__('ui.notifications.activity_cancelled_email_subject', ['activity' => $this->activity->name]))
            ->line(__('ui.notifications.activity_cancelled_email_intro', [
                'activity' => $this->activity->name,
                'by' => $cancelledByName,
            ]));

        if ($event instanceof Event) {
            $message->line(__('ui.notifications.event_label', ['name' => $event->name]));
        }

        $reason = $this->activity->cancel_reason;
        if (is_string($reason) && $reason !== '') {
            $message->line(__('ui.notifications.cancel_reason_email_label', ['reason' => $reason]));
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
        $cancelledByName = $this->cancelledBy->nickname ?? $this->cancelledBy->email ?? __('ui.common.unknown_user');

        return [
            'type' => 'activity_cancelled',
            'activity_id' => $this->activity->id,
            'activity_name' => $this->activity->name,
            'event_id' => $event?->id,
            'event_name' => $event?->name,
            'cancel_reason' => $this->activity->cancel_reason,
            'cancelled_by_display' => $cancelledByName,
            'url' => route('activities.show', ['activity' => $this->activity, 'tab' => 'participation'], false),
            'toast_title' => __('ui.notifications.activity_cancelled_list'),
            'toast_description' => __('ui.notifications.activity_cancelled_toast', [
                'activity' => $this->activity->name,
                'by' => $cancelledByName,
            ]),
        ];
    }
}
