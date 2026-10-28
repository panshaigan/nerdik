<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Contracts\ProvidesSentEmailContext;
use App\Enums\SentEmailKind;
use App\Support\RichText;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminCustomEmailNotification extends Notification implements ProvidesSentEmailContext, ShouldQueue, ShouldQueueAfterCommit
{
    use Queueable;

    public function __construct(
        public string $subject,
        public string $htmlBody,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $htmlBody = RichText::html($this->htmlBody)->toHtml();
        $textBody = RichText::excerpt($this->htmlBody, 10_000);

        return (new MailMessage)
            ->subject($this->subject)
            ->markdown('mail.admin-custom', [
                'htmlBody' => $htmlBody,
                'textBody' => $textBody,
            ]);
    }

    public function sentEmailKind(): SentEmailKind
    {
        return SentEmailKind::AdminCustom;
    }

    public function sentEmailRelated(): ?Model
    {
        return null;
    }
}
