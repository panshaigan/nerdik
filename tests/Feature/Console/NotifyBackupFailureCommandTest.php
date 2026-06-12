<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Mail\BackupFailedMail;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class NotifyBackupFailureCommandTest extends TestCase
{
    #[Test]
    public function it_sends_failure_email_to_legal_contact_email(): void
    {
        Mail::fake();

        config([
            'legal.contact_email' => 'ops@example.com',
        ]);

        $this->artisan('backup:notify-failure', [
            '--message' => 'pg_dump failed: connection refused',
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('Backup failure notification sent to ops@example.com');

        Mail::assertSent(BackupFailedMail::class, function (BackupFailedMail $mail): bool {
            return $mail->hasTo('ops@example.com')
                && $mail->errorMessage === 'pg_dump failed: connection refused';
        });
    }

    #[Test]
    public function it_fails_when_legal_contact_email_is_missing(): void
    {
        Mail::fake();

        config([
            'legal.contact_email' => null,
        ]);

        $this->artisan('backup:notify-failure', [
            '--message' => 'upload failed',
        ])
            ->assertFailed()
            ->expectsOutputToContain('LEGAL_CONTACT_EMAIL is not configured');

        Mail::assertNothingSent();
    }
}
