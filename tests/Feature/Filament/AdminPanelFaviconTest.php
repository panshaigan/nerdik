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
            ->assertSee('viewBox="0 0 1271 1180"', false)
            ->assertSee('fill="var(--brand-logo-primary, #021A2A)"', false);

        $faviconSvg = file_get_contents(public_path('favicon.svg'));

        $this->assertIsString($faviconSvg);
        $this->assertStringContainsString('fill="#021A2A"', $faviconSvg);
        $this->assertStringContainsString('fill="#FDFDFD"', $faviconSvg);
    }
}
