<?php

namespace App\Notifications;

use App\Models\Activity;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WaitlistPromotedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Activity $activity
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database'];
        if ($notifiable->notify_email_waitlist_promoted ?? true) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('You got a place on :activity', ['activity' => $this->activity->name]))
            ->line(__('You have been moved from the waitlist and are now a participant in the activity:'))
            ->line($this->activity->name)
            ->action(__('View activity'), route('activities.show', $this->activity));
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
            'url' => route('activities.show', $this->activity),
        ];
    }
}
