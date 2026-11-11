<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Filament\Admin\Widgets\PlatformStatsOverviewWidget;
use App\Models\User;
use App\Services\Platform\PlatformOnlineMembersQuery;
use App\Services\Platform\PlatformStatsService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PlatformStatsOverviewWidgetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::forget(PlatformStatsService::CACHE_KEY);
        Cache::forget(PlatformOnlineMembersQuery::CACHE_KEY);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    #[Test]
    public function admin_can_see_platform_stats_widget_on_dashboard(): void
    {
        User::factory()->count(2)->create();
        $admin = User::factory()->admin()->create();
        $onlineMember = User::factory()->create();

        DB::table('user_presence')->insert([
            'user_id' => $onlineMember->id,
            'last_seen_at' => now(),
        ]);

        $stats = app(PlatformStatsService::class)->stats(withOnlineMembers: true);

        Livewire::actingAs($admin)
            ->test(PlatformStatsOverviewWidget::class)
            ->assertSee((string) $stats->membersCount)
            ->assertSee((string) $stats->engagedMembersCount)
            ->assertSee((string) $stats->onlineMembersCount)
            ->assertSee(__('ui.platform_stats.online_members'))
            ->assertSee($stats->onlineMembersWindowDescription())
            ->assertSee(__('ui.platform_stats.engaged_ratio', ['percent' => $stats->engagementRatioPercent()]), false);
    }
}
