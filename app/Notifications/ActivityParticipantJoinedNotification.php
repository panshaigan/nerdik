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

class ActivityParticipantJoinedNotification extends Notification implements ShouldQueue, ShouldQueueAfterCommit
{
    use BroadcastsWithDatabasePayload;
    use Queueable;
    use RespectsNotificationPreferences;

    public function __construct(
        public Activity $activity,
        public User $joiner,
        public int $participantCount,
    ) {}

    protected function notificationPreferenceKey(): NotificationPreferenceKey
    {
        return NotificationPreferenceKey::ActivityParticipantJoined;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject($this->subject())
            ->line(__('ui.notifications.activity_participant_joined_line_1', [
                'name' => $this->joinerDisplayName(),
            ]))
            ->line($this->countLine());

        if ($this->isFull()) {
            $message->line(__('ui.notifications.activity_roster_full_line'));
        } elseif ($this->isMinReached()) {
            $message->line(__('ui.notifications.activity_participant_min_reached_line'));
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
            'type' => 'activity_participant_joined',
            'activity_id' => $this->activity->id,
            'activity_name' => $this->activity->name,
            'joiner_id' => $this->joiner->id,
            'joiner_display' => $this->joinerDisplayName(),
            'participant_count' => $this->participantCount,
            'min_participants' => $this->activity->min_participants,
            'max_participants' => $this->activity->max_participants,
            'is_full' => $this->isFull(),
            'min_reached' => $this->isMinReached(),
            'url' => route('activities.show', ['activity' => $this->activity, 'tab' => 'participation'], false),
            'toast_title' => $this->subject(),
            'toast_description' => __('ui.notifications.activity_label', ['name' => $this->activity->name]),
        ];
    }

    protected function isFull(): bool
    {
        $max = $this->activity->max_participants;

        return $max !== null && $this->participantCount >= (int) $max;
    }

    protected function isMinReached(): bool
    {
        $min = $this->activity->min_participants;

        return $min !== null && $this->participantCount >= (int) $min;
    }

    protected function subject(): string
    {
        if ($this->isFull()) {
            return __('ui.notifications.activity_roster_full_subject', [
                'activity' => $this->activity->name,
            ]);
        }

        return __('ui.notifications.activity_participant_joined_subject', [
            'name' => $this->joinerDisplayName(),
            'activity' => $this->activity->name,
        ]);
    }

    protected function countLine(): string
    {
        $max = $this->activity->max_participants;

        if ($max === null) {
            return __('ui.notifications.activity_participant_count_line_no_max', [
                'count' => $this->participantCount,
            ]);
        }

        return __('ui.notifications.activity_participant_count_line', [
            'count' => $this->participantCount,
            'max' => (int) $max,
        ]);
    }

    protected function joinerDisplayName(): string
    {
        return $this->joiner->nickname ?? $this->joiner->email ?? __('ui.common.unknown_user');
    }
}
