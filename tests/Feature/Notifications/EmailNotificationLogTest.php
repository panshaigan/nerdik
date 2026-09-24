<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Enums\SentEmailKind;
use App\Models\Activity;
use App\Models\SentEmail;
use App\Models\User;
use App\Notifications\WaitlistPromotedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Mime\Address;
use Tests\TestCase;

class EmailNotificationLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('email_logs');
    }

    public function test_mail_notification_creates_email_log_entry(): void
    {
        config([
            'legal.contact_email' => 'ops@example.com',
        ]);

        $user = User::factory()->create();
        $activity = Activity::factory()->create();

        $user->notify(new WaitlistPromotedNotification($activity));

        $this->assertSame(1, SentEmail::query()->count());

        $row = SentEmail::query()->first();

        $this->assertNotNull($row);
        $this->assertSame(WaitlistPromotedNotification::class, $row->source_class);
        $this->assertSame(SentEmailKind::WaitlistPromoted, $row->kind);
        $this->assertSame((int) $user->id, (int) $row->recipient_user_id);
        $this->assertSame(mb_strtolower($user->email), $row->recipient_email);
        $this->assertNotNull($row->sent_at);
        $this->assertNotSame('', $row->subject);
        $this->assertSame('activity', $row->related_type);
        $this->assertSame((string) $activity->id, (string) $row->related_id);
        $this->assertNotNull($row->html_path);
        $this->assertNotNull($row->text_path);
        Storage::disk('email_logs')->assertExists($row->html_path);
        Storage::disk('email_logs')->assertExists($row->text_path);
        $this->assertNotSame('', (string) $row->htmlBody());
        $this->assertNotSame('', (string) $row->textBody());
        $this->assertStringContainsString(
            __('ui.notifications.mail_do_not_reply', ['email' => 'ops@example.com']),
            (string) $row->textBody(),
        );
    }

    public function test_verify_email_notification_creates_single_email_log_entry(): void
    {
        $user = User::factory()->unverified()->create();

        $user->sendEmailVerificationNotification();

        $this->assertSame(1, SentEmail::query()->count());

        $row = SentEmail::query()->first();

        $this->assertNotNull($row);
        $this->assertSame(SentEmailKind::VerifyEmail, $row->kind);
        $this->assertSame(mb_strtolower($user->email), $row->recipient_email);
        $this->assertSame((int) $user->id, (int) $row->recipient_user_id);
    }

    public function test_pending_email_verification_logs_pending_address_and_user(): void
    {
        $user = User::factory()->create([
            'email' => 'current@example.com',
            'pending_email' => 'new@example.com',
        ]);

        $user->sendPendingEmailVerificationNotification();

        $this->assertSame(1, SentEmail::query()->count());

        $row = SentEmail::query()->first();

        $this->assertNotNull($row);
        $this->assertSame(SentEmailKind::VerifyPendingEmail, $row->kind);
        $this->assertSame('new@example.com', $row->recipient_email);
        $this->assertSame((int) $user->id, (int) $row->recipient_user_id);
    }

    public function test_backup_failure_mailable_creates_email_log_entry(): void
    {
        config([
            'legal.contact_email' => 'ops@example.com',
        ]);

        $this->artisan('backup:notify-failure', [
            '--message' => 'pg_dump failed',
        ])->assertSuccessful();

        $row = SentEmail::query()->first();

        $this->assertNotNull($row);
        $this->assertSame(SentEmailKind::BackupFailed, $row->kind);
        $this->assertSame('ops@example.com', $row->recipient_email);
        $this->assertNull($row->recipient_user_id);
        $this->assertSame('Nerdik backup failed', $row->subject);
        $this->assertNotNull($row->text_path);
        Storage::disk('email_logs')->assertExists($row->text_path);
    }

    public function test_mail_notification_sets_reply_to_from_legal_contact_email(): void
    {
        config([
            'mail.reply_to' => [
                'address' => 'support@example.com',
                'name' => 'Nerdik Support',
            ],
        ]);
        Mail::purge();

        /** @var list<Address>|null $capturedReplyTo */
        $capturedReplyTo = null;
        Event::listen(MessageSending::class, function (MessageSending $event) use (&$capturedReplyTo): void {
            $capturedReplyTo = $event->message->getReplyTo();
        });

        $user = User::factory()->create();
        $activity = Activity::factory()->create();

        $user->notify(new WaitlistPromotedNotification($activity));

        $this->assertNotNull($capturedReplyTo);
        $this->assertNotEmpty($capturedReplyTo);

        $addresses = array_map(
            static fn ($address): string => $address->getAddress(),
            $capturedReplyTo,
        );
        $this->assertContains('support@example.com', $addresses);

        $match = collect($capturedReplyTo)->first(
            static fn ($address): bool => $address->getAddress() === 'support@example.com',
        );
        $this->assertNotNull($match);
        $this->assertSame('Nerdik Support', $match->getName());
    }

    public function test_mail_fake_does_not_create_email_log_entry(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $activity = Activity::factory()->create();

        $user->notify(new WaitlistPromotedNotification($activity));

        $this->assertSame(0, SentEmail::query()->count());
    }
}
