<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Contracts\ProvidesSentEmailContext;
use App\Enums\SentEmailKind;
use App\Models\Feedback;
use App\Models\User;
use App\Notifications\Concerns\BroadcastsWithDatabasePayload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FeedbackRepliedNotification extends Notification implements ProvidesSentEmailContext, ShouldQueue, ShouldQueueAfterCommit
{
    use BroadcastsWithDatabasePayload;
    use Queueable;

    public function __construct(
        public Feedback $feedback
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        if ($notifiable instanceof User) {
            return ['database', 'broadcast'];
        }

        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('feedback.notifications.replied_mail_subject', [
                'subject' => $this->feedback->subject,
            ]))
            ->line(__('feedback.notifications.replied_mail_intro', [
                'subject' => $this->feedback->subject,
            ]))
            ->line($this->feedback->admin_reply ?? '');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'feedback_replied',
            'feedback_id' => $this->feedback->id,
            'subject' => $this->feedback->subject,
            'admin_reply' => $this->feedback->admin_reply,
            'url' => route('notifications.index', [], false),
            'toast_title' => __('feedback.notifications.replied_title'),
            'toast_description' => __('feedback.notifications.replied_subtitle', [
                'subject' => $this->feedback->subject,
            ]),
        ];
    }

    public function sentEmailKind(): SentEmailKind
    {
        return SentEmailKind::FeedbackReply;
    }

    public function sentEmailRelated(): ?Model
    {
        return $this->feedback;
    }
}
