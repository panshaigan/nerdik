<?php

namespace App\Notifications;

use App\Models\ActivityProposal;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProposalAcceptedNotification extends Notification implements ShouldQueue
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
        $eventName = $this->proposal->eventInstance->event->name ?? __('the event');

        return (new MailMessage)
            ->subject(__('Proposal accepted: :activity', ['activity' => $activity->name]))
            ->line(__('Your activity proposal has been accepted.'))
            ->line(__('Activity: :name', ['name' => $activity->name]))
            ->line(__('Event: :name', ['name' => $eventName]))
            ->action(__('View event'), route('event-instances.show', $this->proposal->eventInstance));
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
            'event_name' => $this->proposal->eventInstance->event->name ?? null,
            'event_instance_id' => $this->proposal->event_instance_id,
            'url' => route('event-instances.show', $this->proposal->eventInstance),
        ];
    }
}
