<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Mail\BackupFailedMail;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

#[Signature('backup:notify-failure {--message= : Error message from the backup script}')]
#[Description('Email LEGAL_CONTACT_EMAIL when a production backup fails')]
final class NotifyBackupFailureCommand extends Command
{
    public function handle(): int
    {
        $recipient = config('legal.contact_email');

        if (! is_string($recipient) || $recipient === '') {
            $this->error('LEGAL_CONTACT_EMAIL is not configured.');

            return self::FAILURE;
        }

        $message = (string) $this->option('message');
        if ($message === '') {
            $message = 'No error message was provided.';
        }

        Mail::to($recipient)->send(new BackupFailedMail(
            errorMessage: $message,
            timestamp: now()->toDateTimeString(),
            hostname: gethostname() ?: 'unknown',
        ));

        $this->info("Backup failure notification sent to {$recipient}.");

        return self::SUCCESS;
    }
}
