<?php

namespace App\Notifications;

use App\Models\Activity;
use App\Notifications\Concerns\BroadcastsWithDatabasePayload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WaitlistPromotedNotification extends Notification implements ShouldQueue, ShouldQueueAfterCommit
{
    use BroadcastsWithDatabasePayload;
    use Queueable;

    public function __construct(
        public Activity $activity
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database', 'broadcast'];
        if ($notifiable->notify_email_waitlist_promoted ?? true) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('ui.notifications.waitlist_promoted_subject', ['activity' => $this->activity->name]))
            ->line(__('ui.notifications.waitlist_promoted_line_1'))
            ->line($this->activity->name)
            ->action(__('ui.notifications.view_activity'), route('activities.show', $this->activity));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'waitlist_promoted',
            'activity_id' => $this->activity->id,
            'activity_name' => $this->activity->name,
            'url' => route('activities.show', ['activity' => $this->activity, 'tab' => 'participation'], false),
            'toast_title' => __('You got a place!'),
            'toast_description' => __('ui.notifications.activity_label', ['name' => $this->activity->name]),
        ];
    }
}
