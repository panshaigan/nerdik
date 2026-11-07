<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Contracts\ProvidesSentEmailContext;
use App\Enums\SentEmailKind;
use App\Models\Feedback;
use App\Models\User;
use App\Notifications\Concerns\BroadcastsWithDatabasePayload;
use App\Support\RichText;
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
            return ['database', 'broadcast', 'mail'];
        }

        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $replyHtml = (string) ($this->feedback->admin_reply ?? '');
        $htmlBody = RichText::html($replyHtml)->toHtml();
        $textBody = RichText::excerpt($replyHtml, 10_000);

        return (new MailMessage)
            ->subject(__('feedback.notifications.replied_mail_subject', [
                'subject' => $this->feedback->subject,
            ]))
            ->markdown('mail.feedback-replied', [
                'subject' => $this->feedback->subject,
                'htmlBody' => $htmlBody,
                'textBody' => $textBody,
            ]);
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
            'admin_reply' => RichText::excerpt($this->feedback->admin_reply, 500),
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
