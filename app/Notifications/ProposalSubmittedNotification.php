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
            ->subject(__('New proposal for :event', ['event' => $event->name]))
            ->line(__('A new activity proposal was submitted for your event.'))
            ->line(__('Activity: :name', ['name' => $activity->name]))
            ->line(__('Proposed by: :name', ['name' => $proposer?->nickname ?? $proposer?->email ?? __('Unknown user')]))
            ->action(__('Review proposal'), route('events.show', $event));
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
