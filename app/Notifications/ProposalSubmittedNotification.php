<?php

namespace App\Notifications;

use App\Models\ActivityProposal;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProposalSubmittedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public ActivityProposal $proposal
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database'];
        if ($notifiable->notify_email_proposal_updates ?? true) {
            $channels[] = 'mail';
        }

        return $channels;
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
        return [
            'type' => 'proposal_submitted',
            'proposal_id' => $this->proposal->id,
            'activity_name' => $this->proposal->activity->name,
            'event_name' => $this->proposal->event->name ?? null,
            'url' => route('events.show', $this->proposal->event),
        ];
    }
}
