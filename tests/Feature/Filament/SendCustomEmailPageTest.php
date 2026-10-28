<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Filament\Admin\Pages\SendCustomEmail;
use App\Jobs\SendAdminCustomEmailToUserJob;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class SendCustomEmailPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    #[Test]
    public function non_admin_cannot_access_send_custom_email_page(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)
            ->get(SendCustomEmail::getUrl())
            ->assertNotFound();
    }

    #[Test]
    public function admin_can_access_send_custom_email_page(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(SendCustomEmail::getUrl())
            ->assertOk();
    }

    #[Test]
    public function send_bulk_action_queues_jobs_for_selected_users(): void
    {
        Queue::fake();

        $admin = User::factory()->admin()->create();
        $recipient = User::factory()->create([
            'email_verified_at' => now(),
            'is_admin' => false,
        ]);

        Livewire::actingAs($admin)
            ->test(SendCustomEmail::class)
            ->set('composeData.subject', 'Hello')
            ->set('composeData.bodyHtml', '<p>Hi</p>')
            ->callTableBulkAction('send', [$recipient]);

        Queue::assertPushed(SendAdminCustomEmailToUserJob::class, 1);
    }
}
