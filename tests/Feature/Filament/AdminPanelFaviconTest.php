<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Models\User;
use Filament\Pages\Dashboard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AdminPanelFaviconTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_dashboard_includes_configured_favicon(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(Dashboard::getUrl())
            ->assertOk()
            ->assertSee('<link rel="icon" href="'.e(asset('favicon.svg')).'" />', false)
            ->assertSee('viewBox="100 88 824 824"', false)
            ->assertSee('fill="#0C2747"', false);
    }
}
