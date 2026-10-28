<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Filament\Admin\Resources\Users\Pages\ListUsers;
use App\Jobs\SendAdminCustomEmailToUserJob;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class UsersBulkCustomEmailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    #[Test]
    public function bulk_action_queues_mail_for_eligible_selection_only(): void
    {
        Queue::fake();

        $admin = User::factory()->admin()->create();
        $eligible = User::factory()->create([
            'email_verified_at' => now(),
            'is_admin' => false,
        ]);
        $ineligible = User::factory()->admin()->create([
            'email_verified_at' => now(),
        ]);

        Livewire::actingAs($admin)
            ->test(ListUsers::class)
            ->callTableBulkAction('sendCustomEmail', [$eligible, $ineligible], data: [
                'subject' => 'Bulk hello',
                'bodyHtml' => '<p>Hi there</p>',
            ]);

        Queue::assertPushed(SendAdminCustomEmailToUserJob::class, 1);
        Queue::assertPushed(SendAdminCustomEmailToUserJob::class, function (SendAdminCustomEmailToUserJob $job) use ($eligible): bool {
            return $job->recipientUserId === (int) $eligible->id;
        });
    }
}
