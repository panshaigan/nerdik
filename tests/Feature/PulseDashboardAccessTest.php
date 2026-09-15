<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PulseDashboardAccessTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function guests_receive_not_found_for_pulse_dashboard(): void
    {
        $this->get($this->pulseDashboardUrl())
            ->assertNotFound();
    }

    #[Test]
    public function non_admins_receive_not_found_for_pulse_dashboard(): void
    {
        $user = User::factory()->create([
            'is_admin' => false,
        ]);

        $this->actingAs($user)
            ->get($this->pulseDashboardUrl())
            ->assertNotFound();
    }

    #[Test]
    public function admins_can_access_pulse_dashboard(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get($this->pulseDashboardUrl())
            ->assertOk();
    }

    private function pulseDashboardUrl(): string
    {
        return '/'.trim((string) config('pulse.path'), '/');
    }
}
