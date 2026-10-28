<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Jobs\SendAdminCustomEmailToUserJob;
use App\Models\User;
use App\Services\AdminEmail\AdminCustomEmailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AdminCustomEmailServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function dispatch_queues_job_for_eligible_recipient(): void
    {
        Queue::fake();

        $admin = User::factory()->admin()->create();
        $recipient = User::factory()->create([
            'email_verified_at' => now(),
            'is_admin' => false,
        ]);

        $service = app(AdminCustomEmailService::class);

        $result = $service->dispatchToRecipients(
            $admin,
            'Hello {name}',
            '<p>Body</p>',
            [$recipient->id],
        );

        $this->assertSame(1, $result->queued);
        $this->assertSame(0, $result->skippedIneligible);

        Queue::assertPushed(SendAdminCustomEmailToUserJob::class, function (SendAdminCustomEmailToUserJob $job) use ($recipient): bool {
            return $job->recipientUserId === (int) $recipient->id
                && $job->subjectTemplate === 'Hello {name}'
                && str_contains($job->bodyHtmlTemplate, '<p>Body</p>');
        });
    }

    #[Test]
    public function dispatch_skips_ineligible_admin_recipient(): void
    {
        Queue::fake();

        $admin = User::factory()->admin()->create();
        $otherAdmin = User::factory()->admin()->create([
            'email_verified_at' => now(),
        ]);

        $service = app(AdminCustomEmailService::class);

        $this->expectException(ValidationException::class);

        $service->dispatchToRecipients(
            $admin,
            'Subject',
            '<p>Body</p>',
            [$otherAdmin->id],
        );
    }

    #[Test]
    public function test_send_queues_job_with_bypass_eligibility(): void
    {
        Queue::fake();

        $admin = User::factory()->admin()->create([
            'email_verified_at' => now(),
        ]);

        app(AdminCustomEmailService::class)->dispatchTestToSender(
            $admin,
            'Test subject',
            '<p>Test</p>',
        );

        Queue::assertPushed(SendAdminCustomEmailToUserJob::class, function (SendAdminCustomEmailToUserJob $job) use ($admin): bool {
            return $job->recipientUserId === (int) $admin->id
                && $job->bypassEligibility === true;
        });
    }
}
