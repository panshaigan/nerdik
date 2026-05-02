<?php

namespace App\Notifications;

use App\Models\Activity;
use App\Models\User;
use App\Notifications\Concerns\BroadcastsWithDatabasePayload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Notification;

class ActivityCancelledNotification extends Notification implements ShouldQueue, ShouldQueueAfterCommit
{
    use BroadcastsWithDatabasePayload;
    use Queueable;

    public function __construct(
        public Activity $activity,
        public User $cancelledBy
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
