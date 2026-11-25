<?php

namespace App\Notifications;

use App\Enums\NotificationPreferenceKey;
use App\Models\Activity;
use App\Notifications\Concerns\AttachesCalendarIcs;
use App\Notifications\Concerns\BroadcastsWithDatabasePayload;
use App\Notifications\Concerns\RespectsNotificationPreferences;
use App\Support\Calendar\CalendarLinks;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WaitlistPromotedNotification extends Notification implements ShouldQueue, ShouldQueueAfterCommit
{
    use AttachesCalendarIcs;
    use BroadcastsWithDatabasePayload;
    use Queueable;
    use RespectsNotificationPreferences;

    public function __construct(
        public Activity $activity
    ) {}

    protected function notificationPreferenceKey(): NotificationPreferenceKey
    {
        return NotificationPreferenceKey::WaitlistPromoted;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject(__('ui.notifications.waitlist_promoted_subject', ['activity' => $this->activity->name]))
            ->line(__('ui.notifications.waitlist_promoted_line_1'))
            ->line($this->activity->name);

        if (filled(rich_text_excerpt($this->activity->participants_only_description))) {
            $message->line(__('ui.notifications.waitlist_promoted_participants_info_heading'));
            $participantsPlain = trim(strip_tags((string) rich_text($this->activity->participants_only_description)));
            if ($participantsPlain !== '') {
                $message->line($participantsPlain);
            }
            $message->line(__('ui.notifications.waitlist_promoted_participants_info_on_page'));
        }

        $message->action(
            __('ui.notifications.view_activity'),
            route('activities.show', ['activity' => $this->activity, 'tab' => 'info'])
        );

        return $this->attachCalendarIcs($message, app(CalendarLinks::class)->forActivity($this->activity));
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
