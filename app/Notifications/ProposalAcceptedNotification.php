<?php

namespace App\Notifications;

use App\Models\ActivityProposal;
use App\Notifications\Concerns\BroadcastsWithDatabasePayload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProposalAcceptedNotification extends Notification implements ShouldQueue, ShouldQueueAfterCommit
{
    use BroadcastsWithDatabasePayload;
    use Queueable;

    public function __construct(
        public ActivityProposal $proposal
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database', 'broadcast'];
        if ($notifiable->notify_email_proposal_updates ?? true) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $activity = $this->proposal->activity;
        $eventName = $this->proposal->event->name ?? __('ui.proposals.event');

        return (new MailMessage)
            ->subject(__('ui.notifications.proposal_accepted_subject', ['activity' => $activity->name]))
            ->line(__('ui.notifications.proposal_accepted_line_1'))
            ->line(__('ui.notifications.activity_label', ['name' => $activity->name]))
            ->line(__('ui.notifications.event_label', ['name' => $eventName]))
            ->action(__('ui.notifications.view_event'), route('events.show', $this->proposal->event));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'proposal_accepted',
            'proposal_id' => $this->proposal->id,
            'activity_name' => $this->proposal->activity->name,
            'event_name' => $this->proposal->event->name ?? null,
            'event_id' => $this->proposal->event_id,
            'url' => route('events.show', $this->proposal->event),
        ];
    }
}
