<?php

namespace App\Notifications;

use App\Enums\NotificationPreferenceKey;
use App\Models\Activity;
use App\Models\User;
use App\Notifications\Concerns\BroadcastsWithDatabasePayload;
use App\Notifications\Concerns\RespectsNotificationPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ActivityParticipantLeftNotification extends Notification implements ShouldQueue, ShouldQueueAfterCommit
{
    use BroadcastsWithDatabasePayload;
    use Queueable;
    use RespectsNotificationPreferences;

    public function __construct(
        public Activity $activity,
        public User $leaver,
        public int $participantCountAfter,
        public ?User $promotedFromWaitlist = null,
    ) {}

    protected function notificationPreferenceKey(): NotificationPreferenceKey
    {
        return NotificationPreferenceKey::ActivityParticipantLeft;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject($this->subject())
            ->line(__('ui.notifications.activity_participant_left_line_1', [
                'name' => $this->leaverDisplayName(),
            ]));

        if ($this->promotedFromWaitlist instanceof User) {
            $message->line(__('ui.notifications.activity_participant_left_replaced_line', [
                'replacement' => $this->promotedDisplayName(),
            ]));
        } elseif ($this->isBelowMinimum()) {
            $message->line(__('ui.notifications.activity_participant_below_min_line', [
                'count' => $this->participantCountAfter,
                'min' => (int) $this->activity->min_participants,
            ]));
        }

        return $message->action(
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
            'type' => 'activity_participant_left',
            'activity_id' => $this->activity->id,
            'activity_name' => $this->activity->name,
            'leaver_id' => $this->leaver->id,
            'leaver_display' => $this->leaverDisplayName(),
            'promoted_user_id' => $this->promotedFromWaitlist?->id,
            'promoted_display' => $this->promotedFromWaitlist instanceof User
                ? $this->promotedDisplayName()
                : null,
            'participant_count' => $this->participantCountAfter,
            'min_participants' => $this->activity->min_participants,
            'max_participants' => $this->activity->max_participants,
            'below_minimum' => $this->isBelowMinimum() && ! $this->promotedFromWaitlist instanceof User,
            'url' => route('activities.show', ['activity' => $this->activity, 'tab' => 'participation'], false),
            'toast_title' => $this->subject(),
            'toast_description' => __('ui.notifications.activity_label', ['name' => $this->activity->name]),
        ];
    }

    protected function isBelowMinimum(): bool
    {
        $min = $this->activity->min_participants;

        return $min !== null && $this->participantCountAfter < (int) $min;
    }

    protected function subject(): string
    {
        if ($this->promotedFromWaitlist instanceof User) {
            return __('ui.notifications.activity_participant_left_replaced_subject', [
                'name' => $this->leaverDisplayName(),
                'activity' => $this->activity->name,
                'replacement' => $this->promotedDisplayName(),
            ]);
        }

        if ($this->isBelowMinimum()) {
            return __('ui.notifications.activity_participant_left_below_min_subject', [
                'name' => $this->leaverDisplayName(),
                'activity' => $this->activity->name,
            ]);
        }

        return __('ui.notifications.activity_participant_left_subject', [
            'name' => $this->leaverDisplayName(),
            'activity' => $this->activity->name,
        ]);
    }

    protected function leaverDisplayName(): string
    {
        return $this->leaver->displayName();
    }

    protected function promotedDisplayName(): string
    {
        $user = $this->promotedFromWaitlist;
        if (! $user instanceof User) {
            return __('ui.common.unknown_user');
        }

        return $user->displayName();
    }
}
