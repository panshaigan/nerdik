<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationPreferenceKey;
use App\Enums\UserRequestType;
use App\Models\User;
use App\Models\UserRequest;
use App\Notifications\Concerns\BroadcastsWithDatabasePayload;
use App\Notifications\Concerns\LogsSentEmailContext;
use App\Services\Notifications\NotificationDispatchThrottle;
use App\Services\UserRequests\UserRequestSubjectLabelResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserRequestReceivedNotification extends Notification implements ShouldQueue, ShouldQueueAfterCommit
{
    use BroadcastsWithDatabasePayload;
    use LogsSentEmailContext;
    use Queueable;

    public function __construct(
        public UserRequest $request
    ) {}

    protected function notificationPreferenceKey(): NotificationPreferenceKey
    {
        return NotificationPreferenceKey::UserRequests;
    }

    /**
     * Incoming requests use the requests inbox in-app (via broadcast refresh), not the bell.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        if (! $notifiable instanceof User) {
            return ['mail', 'broadcast'];
        }

        if (app(NotificationDispatchThrottle::class)->shouldSuppress($this, $notifiable)) {
            return [];
        }

        $channels = ['broadcast'];

        if ($notifiable->wantsNotificationChannel($this->notificationPreferenceKey(), 'email')) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $this->request->loadMissing(['requester', 'subject']);
        $requesterName = $this->request->requester?->displayName() ?? __('ui.common.unknown_user');
        $subjectLabel = app(UserRequestSubjectLabelResolver::class)->resolve($this->request);

        return (new MailMessage)
            ->subject($this->mailSubject())
            ->line($this->mailIntro($requesterName, $subjectLabel))
            ->when($this->request->message, fn (MailMessage $mail) => $mail->line($this->request->message))
            ->action(__('ui.user_requests.review_request'), route('requests.index', ['request' => $this->request->id]));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $this->request->loadMissing(['requester', 'subject']);
        $requesterName = $this->request->requester?->displayName() ?? __('ui.common.unknown_user');
        $subjectLabel = app(UserRequestSubjectLabelResolver::class)->resolve($this->request);

        return [
            'type' => 'user_request_received',
            'request_id' => $this->request->id,
            'request_type' => $this->request->type->value,
            'actionable' => true,
            'requester_name' => $requesterName,
            'subject_label' => $subjectLabel,
            'message' => $this->request->message,
            'expires_at' => $this->request->expires_at?->toIso8601String(),
            'url' => route('requests.index', ['request' => $this->request->id], false),
            'toast_title' => $this->listTitle(),
            'toast_description' => $subjectLabel,
        ];
    }

    private function listTitle(): string
    {
        return match ($this->request->type) {
            UserRequestType::OrganizationInvite => __('ui.user_requests.received_organization_invite'),
            UserRequestType::OrganizationJoinRequest => __('ui.user_requests.received_organization_join'),
            UserRequestType::ActivityInvite => __('ui.user_requests.received_activity_invite'),
            UserRequestType::EventOrganizerFlag => __('ui.user_requests.received_organizer_flag'),
        };
    }

    private function mailSubject(): string
    {
        return $this->listTitle();
    }

    private function mailIntro(string $requesterName, string $subjectLabel): string
    {
        return match ($this->request->type) {
            UserRequestType::OrganizationInvite => __('ui.user_requests.mail_organization_invite', [
                'name' => $requesterName,
                'organization' => $subjectLabel,
            ]),
            UserRequestType::OrganizationJoinRequest => __('ui.user_requests.mail_organization_join', [
                'name' => $requesterName,
                'organization' => $subjectLabel,
            ]),
            UserRequestType::ActivityInvite => __('ui.user_requests.mail_activity_invite', [
                'name' => $requesterName,
                'activity' => $subjectLabel,
            ]),
            UserRequestType::EventOrganizerFlag => __('ui.user_requests.mail_organizer_flag', [
                'name' => $requesterName,
            ]),
        };
    }
}
