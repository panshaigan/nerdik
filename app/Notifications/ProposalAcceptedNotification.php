<?php

namespace App\Notifications;

use App\Enums\NotificationPreferenceKey;
use App\Models\ActivityProposal;
use App\Notifications\Concerns\AttachesCalendarIcs;
use App\Notifications\Concerns\BroadcastsWithDatabasePayload;
use App\Notifications\Concerns\RespectsNotificationPreferences;
use App\Support\Calendar\CalendarLinks;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProposalAcceptedNotification extends Notification implements ShouldQueue, ShouldQueueAfterCommit
{
    use AttachesCalendarIcs;
    use BroadcastsWithDatabasePayload;
    use Queueable;
    use RespectsNotificationPreferences;

    public function __construct(
        public ActivityProposal $proposal
    ) {}

    protected function notificationPreferenceKey(): NotificationPreferenceKey
    {
        return NotificationPreferenceKey::Proposals;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $this->proposal->loadMissing(['activity.slot', 'event']);
        $activity = $this->proposal->activity;
        $eventName = $this->proposal->event->name ?? __('ui.proposals.event');

        $message = (new MailMessage)
            ->subject(__('ui.notifications.proposal_accepted_subject', ['activity' => $activity->name]))
            ->line(__('ui.notifications.proposal_accepted_line_1'))
            ->line(__('ui.notifications.activity_label', ['name' => $activity->name]))
            ->line(__('ui.notifications.event_label', ['name' => $eventName]))
            ->action(__('ui.notifications.view_event'), route('events.show', $this->proposal->event));

        return $this->attachCalendarIcs($message, app(CalendarLinks::class)->forActivity($activity));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $event = $this->proposal->event;

        return [
            'type' => 'proposal_accepted',
            'proposal_id' => $this->proposal->id,
            'activity_name' => $this->proposal->activity->name,
            'event_name' => $event->name ?? null,
            'event_id' => $this->proposal->event_id,
            'url' => route('events.show', ['event' => $event, 'tab' => 'plan'], false),
            'toast_title' => __('Proposal accepted'),
            'toast_description' => __('ui.notifications.activity_label', ['name' => $this->proposal->activity->name]),
        ];
    }
}
