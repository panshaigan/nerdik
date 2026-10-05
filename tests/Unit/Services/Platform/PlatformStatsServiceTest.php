<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Platform;

use App\Models\Activity;
use App\Models\Event;
use App\Models\User;
use App\Services\Platform\PlatformStatsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PlatformStatsServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::forget(PlatformStatsService::CACHE_KEY);
    }

    #[Test]
    public function it_counts_active_members_and_engaged_members(): void
    {
        $host = User::factory()->create();
        $participant = User::factory()->create();
        User::factory()->create(['is_deleted' => true]);
        User::factory()->create();

        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'updated_by' => $host->id,
        ]);

        DB::table('activity_user')->insert([
            'activity_id' => $activity->id,
            'user_id' => $participant->id,
            'is_absent' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $stats = app(PlatformStatsService::class)->stats();

        $this->assertSame(3, $stats->membersCount);
        $this->assertSame(2, $stats->engagedMembersCount);
        $this->assertSame(67, $stats->engagementRatioPercent());
    }

    #[Test]
    public function it_counts_hosts_as_engaged_even_without_roster_entry(): void
    {
        $host = User::factory()->create();

        Activity::factory()->create([
            'created_by' => $host->id,
            'updated_by' => $host->id,
        ]);

        $stats = app(PlatformStatsService::class)->stats();

        $this->assertSame(1, $stats->membersCount);
        $this->assertSame(1, $stats->engagedMembersCount);
        $this->assertSame(100, $stats->engagementRatioPercent());
    }

    #[Test]
    public function it_counts_upcoming_events_and_activities(): void
    {
        $user = User::factory()->create();

        Event::factory()->create([
            'created_by' => $user->id,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(2),
        ]);

        Event::factory()->create([
            'created_by' => $user->id,
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->subDay(),
        ]);

        Activity::factory()->create([
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(2),
        ]);

        $stats = app(PlatformStatsService::class)->stats();

        $this->assertSame(1, $stats->upcomingEventsCount);
        $this->assertSame(1, $stats->upcomingActivitiesCount);
    }

    #[Test]
    public function it_caches_stats_between_calls(): void
    {
        User::factory()->create();

        $service = app(PlatformStatsService::class);
        $first = $service->stats();

        User::factory()->create();

        $second = $service->stats();

        $this->assertSame($first->membersCount, $second->membersCount);
    }
}
