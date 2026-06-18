<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationPreferenceKey;
use App\Enums\UserRequestResolutionOutcome;
use App\Enums\UserRequestStatus;
use App\Enums\UserRequestType;
use App\Models\User;
use App\Models\UserRequest;
use App\Notifications\Concerns\BroadcastsWithDatabasePayload;
use App\Services\Notifications\NotificationDispatchThrottle;
use App\Services\UserRequests\UserRequestSubjectLabelResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserRequestResolvedNotification extends Notification implements ShouldQueue, ShouldQueueAfterCommit
{
    use BroadcastsWithDatabasePayload;
    use Queueable;

    public function __construct(
        public UserRequest $request,
        public ?string $declineNote = null,
    ) {}

    protected function notificationPreferenceKey(): NotificationPreferenceKey
    {
        return NotificationPreferenceKey::UserRequests;
    }

    /**
     * Only the request issuer sees accept/decline outcomes on the bell; broadcast always refreshes requests UI.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        if (! $notifiable instanceof User) {
            return ['database', 'broadcast', 'mail'];
        }

        if (app(NotificationDispatchThrottle::class)->shouldSuppress($this, $notifiable)) {
            return [];
        }

        $this->request->loadMissing('requester');

        $isRequesterDecisionInApp = $this->request->requester !== null
            && (int) $notifiable->id === (int) $this->request->requester_id
            && in_array($this->request->status, [UserRequestStatus::Accepted, UserRequestStatus::Declined], true);

        $channels = ['broadcast'];

        if ($isRequesterDecisionInApp && $notifiable->wantsNotificationChannel($this->notificationPreferenceKey(), 'in_app')) {
            $channels[] = 'database';
        }

        if ($notifiable->wantsNotificationChannel($this->notificationPreferenceKey(), 'email')) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->listTitle())
            ->line($this->bodyLine());

        if ($this->declineNote !== null && trim($this->declineNote) !== '') {
            $mail->line($this->declineNote);
        }

        return $mail;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $this->request->loadMissing(['requester', 'recipient', 'respondedBy', 'subject']);
        $subjectLabel = app(UserRequestSubjectLabelResolver::class)->resolve($this->request);
        $responderName = $this->request->respondedBy?->displayName()
            ?? $this->request->recipient?->displayName()
            ?? __('ui.common.unknown_user');

        return [
            'type' => 'user_request_resolved',
            'request_id' => $this->request->id,
            'request_type' => $this->request->type->value,
            'request_status' => $this->request->status->value,
            'actionable' => false,
            'resolution_outcome' => $this->request->resolution_outcome?->value,
            'responder_name' => $responderName,
            'subject_label' => $subjectLabel,
            'decline_note' => $this->declineNote,
            'url' => route('notifications.index', [], false),
            'toast_title' => $this->listTitle(),
            'toast_description' => $subjectLabel,
        ];
    }

    private function listTitle(): string
    {
        if ($this->request->status === UserRequestStatus::Accepted) {
            if ($this->request->type === UserRequestType::ActivityInvite) {
                return match ($this->request->resolution_outcome) {
                    UserRequestResolutionOutcome::Waitlisted => __('ui.user_requests.resolved_activity_waitlisted'),
                    default => __('ui.user_requests.resolved_activity_joined'),
                };
            }

            return __('ui.user_requests.resolved_accepted');
        }

        return match ($this->request->status) {
            UserRequestStatus::Declined => __('ui.user_requests.resolved_declined'),
            UserRequestStatus::Cancelled => __('ui.user_requests.resolved_cancelled'),
            UserRequestStatus::Expired => __('ui.user_requests.resolved_expired'),
            default => __('ui.user_requests.resolved_generic'),
        };
    }

    private function bodyLine(): string
    {
        $subjectLabel = app(UserRequestSubjectLabelResolver::class)->resolve($this->request);
        $responderName = $this->request->respondedBy?->displayName() ?? __('ui.common.unknown_user');

        return match ($this->request->status) {
            UserRequestStatus::Accepted => __('ui.user_requests.resolved_accepted_body', [
                'subject' => $subjectLabel,
                'name' => $responderName,
            ]),
            UserRequestStatus::Declined => __('ui.user_requests.resolved_declined_body', [
                'subject' => $subjectLabel,
                'name' => $responderName,
            ]),
            UserRequestStatus::Cancelled => __('ui.user_requests.resolved_cancelled_body', [
                'subject' => $subjectLabel,
            ]),
            UserRequestStatus::Expired => __('ui.user_requests.resolved_expired_body', [
                'subject' => $subjectLabel,
            ]),
            default => $subjectLabel,
        };
    }
}
