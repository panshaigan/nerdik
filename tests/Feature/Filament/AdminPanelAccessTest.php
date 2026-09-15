<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AdminPanelAccessTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function panel_path_follows_filament_admin_path_config(): void
    {
        $this->assertSame(
            (string) config('filament.admin_path'),
            Filament::getPanel('admin')->getPath(),
        );
    }

    #[Test]
    public function guests_receive_not_found_for_admin_panel(): void
    {
        $this->get($this->adminDashboardUrl())
            ->assertNotFound();
    }

    #[Test]
    public function non_admins_receive_not_found_for_admin_panel(): void
    {
        $user = User::factory()->create([
            'is_admin' => false,
        ]);

        $this->actingAs($user)
            ->get($this->adminDashboardUrl())
            ->assertNotFound();
    }

    #[Test]
    public function admins_can_access_admin_panel(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get($this->adminDashboardUrl())
            ->assertOk();
    }

    private function adminDashboardUrl(): string
    {
        return '/'.trim((string) config('filament.admin_path'), '/');
    }
}
