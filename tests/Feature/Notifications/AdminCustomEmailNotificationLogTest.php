<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Enums\SentEmailKind;
use App\Models\SentEmail;
use App\Models\User;
use App\Notifications\AdminCustomEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AdminCustomEmailNotificationLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('email_logs');
    }

    #[Test]
    public function admin_custom_notification_creates_email_log_entry(): void
    {
        config([
            'legal.contact_email' => 'ops@example.com',
        ]);

        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $user->notify(new AdminCustomEmailNotification(
            'Custom subject',
            '<p>Custom body</p>',
        ));

        $this->assertSame(1, SentEmail::query()->count());

        $row = SentEmail::query()->first();

        $this->assertNotNull($row);
        $this->assertSame(SentEmailKind::AdminCustom, $row->kind);
        $this->assertSame(AdminCustomEmailNotification::class, $row->source_class);
        $this->assertSame(mb_strtolower($user->email), $row->recipient_email);
        $this->assertSame((int) $user->id, (int) $row->recipient_user_id);
        $this->assertStringContainsString(
            __('ui.notifications.mail_do_not_reply', ['email' => 'ops@example.com']),
            (string) $row->textBody(),
        );
    }
}
