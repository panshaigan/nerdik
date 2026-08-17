<?php

declare(strict_types=1);

namespace App\Mail;

use App\Enums\SentEmailKind;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Mime\Email;

final class BackupFailedMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public string $errorMessage,
        public string $timestamp,
        public string $hostname,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Nerdik backup failed',
            using: [
                function (Email $email): void {
                    $email->getHeaders()->addTextHeader('X-Nerdik-Kind', SentEmailKind::BackupFailed->value);
                    $email->getHeaders()->addTextHeader('X-Nerdik-Source', BackupFailedMail::class);
                },
            ],
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'mail.backup-failed',
        );
    }
}
