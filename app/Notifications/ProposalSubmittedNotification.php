<?php

namespace App\Notifications;

use App\Enums\NotificationPreferenceKey;
use App\Models\ActivityProposal;
use App\Notifications\Concerns\BroadcastsWithDatabasePayload;
use App\Notifications\Concerns\RespectsNotificationPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProposalSubmittedNotification extends Notification implements ShouldQueue, ShouldQueueAfterCommit
{
    use BroadcastsWithDatabasePayload;
    use Queueable;
    use RespectsNotificationPreferences;

    /**
     * Discriminator echoed in broadcasts for JS listeners ({@see lw_event_refresh} is not overwritten by Laravel merges).
     */
    public const string LIVEWIRE_REFRESH_PROPOSAL_SUBMITTED_FOR_EVENT = 'proposal_submitted_for_event';

    public function __construct(
        public ActivityProposal $proposal
    ) {}

    protected function notificationPreferenceKey(): NotificationPreferenceKey
    {
        return NotificationPreferenceKey::Proposals;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $activity = $this->proposal->activity;
        $event = $this->proposal->event;
        $proposer = $this->proposal->creator;

        return (new MailMessage)
            ->subject(__('ui.notifications.proposal_submitted_subject', ['event' => $event->name]))
            ->line(__('ui.notifications.proposal_submitted_line_1'))
            ->line(__('ui.notifications.activity_label', ['name' => $activity->name]))
            ->line(__('ui.notifications.proposal_submitted_line_2', ['name' => $proposer?->nickname ?? $proposer?->email ?? __('ui.common.unknown_user')]))
            ->action(__('ui.notifications.review_proposal'), route('events.show', $event));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $event = $this->proposal->event;

        return [
            'type' => 'proposal_submitted',
            'proposal_id' => $this->proposal->id,
            'lw_event_refresh' => self::LIVEWIRE_REFRESH_PROPOSAL_SUBMITTED_FOR_EVENT,
            'event_id' => $this->proposal->event_id ?? $event?->getKey(),
            'activity_name' => $this->proposal->activity->name,
            'event_name' => $event->name ?? null,
            'url' => route('events.show', ['event' => $event, 'tab' => 'proposals'], false),
            'toast_title' => __('ui.notifications.proposal_submitted_list'),
            'toast_description' => __('ui.notifications.activity_label', ['name' => $this->proposal->activity->name]),
        ];
    }
}
