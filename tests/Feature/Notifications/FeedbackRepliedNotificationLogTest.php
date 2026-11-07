<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Enums\SentEmailKind;
use App\Models\Feedback;
use App\Models\SentEmail;
use App\Models\User;
use App\Notifications\FeedbackRepliedNotification;
use App\Services\Feedback\FeedbackReplyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class FeedbackRepliedNotificationLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('email_logs');
    }

    #[Test]
    public function reply_to_registered_user_creates_email_log_entry(): void
    {
        config([
            'legal.contact_email' => 'ops@example.com',
        ]);

        $admin = User::factory()->admin()->create();
        $reporter = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $feedback = Feedback::factory()->fromUser($reporter)->create([
            'subject' => 'Broken calendar sync',
        ]);

        app(FeedbackReplyService::class)->reply(
            $feedback,
            $admin,
            '<p>Thanks — we fixed it.</p>',
        );

        $this->assertSame(1, SentEmail::query()->count());

        $row = SentEmail::query()->first();

        $this->assertNotNull($row);
        $this->assertSame(SentEmailKind::FeedbackReply, $row->kind);
        $this->assertSame(FeedbackRepliedNotification::class, $row->source_class);
        $this->assertSame(mb_strtolower($reporter->email), $row->recipient_email);
        $this->assertSame((int) $reporter->id, (int) $row->recipient_user_id);
        $this->assertSame($feedback->getMorphClass(), $row->related_type);
        $this->assertSame((string) $feedback->id, (string) $row->related_id);
        $this->assertStringContainsString('Broken calendar sync', (string) $row->subject);
        $this->assertStringContainsString('we fixed it', (string) $row->textBody());
        $this->assertStringContainsString(
            __('ui.notifications.mail_do_not_reply', ['email' => 'ops@example.com']),
            (string) $row->textBody(),
        );
        Storage::disk('email_logs')->assertExists($row->html_path);
        Storage::disk('email_logs')->assertExists($row->text_path);
    }

    #[Test]
    public function reply_to_guest_creates_email_log_entry(): void
    {
        $admin = User::factory()->admin()->create();
        $feedback = Feedback::factory()->guest()->create([
            'email' => 'guest@example.com',
            'subject' => 'Guest question',
        ]);

        app(FeedbackReplyService::class)->reply(
            $feedback,
            $admin,
            '<p>Thanks for reporting.</p>',
        );

        $this->assertSame(1, SentEmail::query()->count());

        $row = SentEmail::query()->first();

        $this->assertNotNull($row);
        $this->assertSame(SentEmailKind::FeedbackReply, $row->kind);
        $this->assertSame('guest@example.com', $row->recipient_email);
        $this->assertNull($row->recipient_user_id);
        $this->assertSame($feedback->getMorphClass(), $row->related_type);
        $this->assertSame((string) $feedback->id, (string) $row->related_id);
        $this->assertStringContainsString('Thanks for reporting', (string) $row->textBody());
    }
}
