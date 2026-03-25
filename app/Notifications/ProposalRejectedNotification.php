<?php

namespace App\Notifications;

use App\Models\ActivityProposal;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProposalRejectedNotification extends Notification implements ShouldQueue
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
        $eventName = $this->proposal->event->name ?? __('the event');

        return (new MailMessage)
            ->subject(__('Proposal rejected: :activity', ['activity' => $activity->name]))
            ->line(__('Your activity proposal has been rejected by the event organizer.'))
            ->line(__('Activity: :name', ['name' => $activity->name]))
            ->line(__('Event: :name', ['name' => $eventName]))
            ->line(__('You can still use this activity for other events or propose it again elsewhere.'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'proposal_rejected',
            'proposal_id' => $this->proposal->id,
            'activity_name' => $this->proposal->activity->name,
            'event_name' => $this->proposal->event->name ?? null,
            'url' => route('activity-proposals.index'),
        ];
    }
}
