<?php

namespace App\Notifications;

use App\Contracts\ProvidesSentEmailContext;
use App\Enums\SentEmailKind;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;

class VerifyPendingEmailNotification extends Notification implements ProvidesSentEmailContext
{
    use Queueable;

    public function __construct(
        public User $user
    ) {}

    public function sentEmailKind(): SentEmailKind
    {
        return SentEmailKind::VerifyPendingEmail;
    }

    public function sentEmailRelated(): ?Model
    {
        return null;
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $pendingEmail = (string) $this->user->pending_email;

        return (new MailMessage)
            ->subject(__('ui.profile.verify_pending_email_subject'))
            ->line(__('ui.profile.verify_pending_email_line_1'))
            ->action(__('ui.profile.verify_pending_email_action'), $this->verificationUrl($pendingEmail))
            ->line(__('ui.profile.verify_pending_email_line_2'));
    }

    protected function verificationUrl(string $pendingEmail): string
    {
        return URL::temporarySignedRoute(
            'profile.email.verify',
            Carbon::now()->addMinutes(Config::get('auth.verification.expire', 60)),
            [
                'id' => $this->user->getKey(),
                'hash' => sha1($pendingEmail),
            ]
        );
    }
}
