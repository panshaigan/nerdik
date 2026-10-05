<?php

declare(strict_types=1);

namespace Tests\Feature\Dashboard;

use App\Livewire\Dashboard\Dashboard;
use App\Models\Activity;
use App\Models\User;
use App\Services\Platform\PlatformStatsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AdminPlatformStatsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::forget(PlatformStatsService::CACHE_KEY);
    }

    #[Test]
    public function admin_dashboard_renders_platform_stats(): void
    {
        $admin = User::factory()->admin()->create();
        $host = User::factory()->create();

        Activity::factory()->create([
            'created_by' => $host->id,
            'updated_by' => $host->id,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(2),
        ]);

        $stats = app(PlatformStatsService::class)->stats();

        Livewire::actingAs($admin)
            ->test(Dashboard::class)
            ->assertViewHas('platformStats', fn ($value) => $value !== null)
            ->assertSeeHtml('data-ui="dashboard-admin-platform-stats"')
            ->assertSee((string) $stats->membersCount, false)
            ->assertSee((string) $stats->engagedMembersCount, false);
    }

    #[Test]
    public function non_admin_dashboard_does_not_render_platform_stats(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(Dashboard::class)
            ->assertViewHas('platformStats', null)
            ->assertDontSeeHtml('data-ui="dashboard-admin-platform-stats"');
    }
}
