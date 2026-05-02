<?php

namespace App\Notifications;

use App\Models\Activity;
use App\Notifications\Concerns\BroadcastsWithDatabasePayload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use InvalidArgumentException;

class ActivityRemovedByHostNotification extends Notification implements ShouldQueue, ShouldQueueAfterCommit
{
    use BroadcastsWithDatabasePayload;
    use Queueable;

    public const string MODE_REMOVED = 'removed';

    public const string MODE_MOVED_TO_WAITLIST = 'moved_to_waitlist';

    public function __construct(
        public Activity $activity,
        public string $mode,
    ) {
        if (! in_array($this->mode, [self::MODE_REMOVED, self::MODE_MOVED_TO_WAITLIST], true)) {
            throw new InvalidArgumentException("Unknown mode: {$this->mode}");
        }
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->subject())
            ->line($this->bodyLine())
            ->line(__('ui.notifications.activity_label', ['name' => $this->activity->name]))
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
            'type' => 'activity_removed_by_host',
            'mode' => $this->mode,
            'activity_id' => $this->activity->id,
            'activity_name' => $this->activity->name,
            'url' => route('activities.show', ['activity' => $this->activity, 'tab' => 'participation'], false),
            'toast_title' => $this->subject(),
            'toast_description' => __('ui.notifications.activity_label', ['name' => $this->activity->name]),
        ];
    }

    protected function subject(): string
    {
        if ($this->mode === self::MODE_MOVED_TO_WAITLIST) {
            return __('ui.notifications.activity_moved_to_waitlist_subject', [
                'activity' => $this->activity->name,
            ]);
        }

        return __('ui.notifications.activity_removed_by_host_subject', [
            'activity' => $this->activity->name,
        ]);
    }

    protected function bodyLine(): string
    {
        if ($this->mode === self::MODE_MOVED_TO_WAITLIST) {
            return __('ui.notifications.activity_moved_to_waitlist_line_1');
        }

        return __('ui.notifications.activity_removed_by_host_line_1');
    }
}
