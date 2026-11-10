<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Jobs\SendAdminCustomEmailToUserJob;
use App\Models\SentEmail;
use App\Models\User;
use App\Notifications\AdminCustomInAppNotification;
use App\Services\AdminEmail\AdminCustomEmailService;
use App\Support\AdminEmail\AdminEmailPlaceholderReplacer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AdminCustomInAppNotificationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function in_app_notification_persists_database_payload(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $user->notify(new AdminCustomInAppNotification(
            'Hello there',
            '<p>Details inside</p>',
            '/search',
        ));

        $this->assertSame(0, SentEmail::query()->count());

        $row = $user->fresh()->notifications()->first();
        $this->assertNotNull($row);

        /** @var array<string, mixed> $data */
        $data = $row->data;

        $this->assertSame('admin_custom_message', $data['type']);
        $this->assertSame('Hello there', $data['subject']);
        $this->assertSame('Hello there', $data['toast_title']);
        $this->assertStringContainsString('Details inside', (string) ($data['body_html'] ?? ''));
        $this->assertSame('/search', $data['url']);
        Mail::assertNothingSent();
    }

    #[Test]
    public function job_delivers_in_app_notification_with_placeholders(): void
    {
        Mail::fake();

        $recipient = User::factory()->create([
            'name' => 'Ada Lovelace',
            'email_verified_at' => now(),
            'is_admin' => false,
        ]);

        $job = new SendAdminCustomEmailToUserJob(
            recipientUserId: (int) $recipient->id,
            subjectTemplate: 'Hi {name}',
            bodyHtmlTemplate: '<p>For {name}</p>',
            sendAsNotification: true,
            inAppClickUrl: '/dashboard',
        );

        $job->handle(
            app(AdminCustomEmailService::class),
            app(AdminEmailPlaceholderReplacer::class),
        );

        $row = $recipient->fresh()->notifications()->first();
        $this->assertNotNull($row);

        /** @var array<string, mixed> $data */
        $data = $row->data;

        $this->assertSame('admin_custom_message', $data['type']);
        $this->assertSame('Hi Ada Lovelace', $data['toast_title']);
        $this->assertStringContainsString('Ada Lovelace', (string) ($data['body_html'] ?? ''));
        $this->assertSame('/dashboard', $data['url']);

        Mail::assertNothingSent();
    }
}
