<?php

declare(strict_types=1);

namespace Tests\Feature\Feedback;

use App\Enums\FeedbackStatus;
use App\Filament\Admin\Resources\Feedback\Pages\ListFeedback;
use App\Filament\Admin\Resources\Feedback\Pages\ViewFeedback;
use App\Models\Feedback;
use App\Models\User;
use App\Notifications\FeedbackRepliedNotification;
use App\Services\Feedback\FeedbackReplyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class FeedbackAdminActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_and_view_feedback(): void
    {
        $admin = User::factory()->admin()->create();
        $feedback = Feedback::factory()->create([
            'subject' => 'Calendar sync broken',
        ]);

        Livewire::actingAs($admin)
            ->test(ListFeedback::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$feedback]);

        Livewire::actingAs($admin)
            ->test(ViewFeedback::class, ['record' => $feedback->id])
            ->assertOk()
            ->assertSee('Calendar sync broken');
    }

    public function test_admin_can_resolve_and_reopen_feedback(): void
    {
        $admin = User::factory()->admin()->create();
        $feedback = Feedback::factory()->create();

        $service = app(FeedbackReplyService::class);

        $resolved = $service->resolve($feedback, $admin);
        $this->assertTrue($resolved->isResolved());
        $this->assertSame($admin->id, $resolved->resolved_by_id);

        $reopened = $service->reopen($resolved);
        $this->assertTrue($reopened->isOpen());
        $this->assertNull($reopened->resolved_at);
        $this->assertNull($reopened->resolved_by_id);
    }

    public function test_reply_to_user_sends_database_notification_and_resolves(): void
    {
        Notification::fake();

        $admin = User::factory()->admin()->create();
        $reporter = User::factory()->create();
        $feedback = Feedback::factory()->fromUser($reporter)->create();

        $replied = app(FeedbackReplyService::class)->reply($feedback, $admin, 'Thanks — we fixed it.');

        $this->assertTrue($replied->hasReply());
        $this->assertSame(FeedbackStatus::Resolved, $replied->status);
        $this->assertSame('Thanks — we fixed it.', $replied->admin_reply);
        $this->assertSame($admin->id, $replied->replied_by_id);

        Notification::assertSentTo($reporter, FeedbackRepliedNotification::class);
    }

    public function test_reply_to_guest_sends_mail_notification(): void
    {
        Notification::fake();

        $admin = User::factory()->admin()->create();
        $feedback = Feedback::factory()->guest()->create([
            'email' => 'guest@example.com',
        ]);

        app(FeedbackReplyService::class)->reply($feedback, $admin, 'Thanks for reporting.');

        Notification::assertSentOnDemand(
            FeedbackRepliedNotification::class,
            function (FeedbackRepliedNotification $notification, array $channels, object $notifiable): bool {
                return in_array('mail', $channels, true)
                    && ($notifiable->routes['mail'] ?? null) === 'guest@example.com'
                    && $notification->feedback->email === 'guest@example.com';
            },
        );
    }

    public function test_second_reply_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $feedback = Feedback::factory()->replied()->create();

        $this->expectException(ValidationException::class);

        app(FeedbackReplyService::class)->reply($feedback, $admin, 'Another reply');
    }
}
