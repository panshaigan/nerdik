<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Livewire\Dashboard\Dashboard;
use App\Models\Activity;
use App\Models\User;
use App\Services\Dashboard\UpcomingFeedQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardActivityStatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_upcoming_activity_stats_exclude_past_activities(): void
    {
        $user = User::factory()->create();
        $pastStart = now()->subDays(3);
        $pastEnd = now()->subDays(2);

        $pastInterested = Activity::factory()->create([
            'name' => 'Past Interested Activity',
            'starts_at' => $pastStart,
            'ends_at' => $pastEnd,
        ]);
        $pastParticipating = Activity::factory()->create([
            'name' => 'Past Participating Activity',
            'starts_at' => $pastStart,
            'ends_at' => $pastEnd,
        ]);
        $pastCreated = Activity::factory()->create([
            'name' => 'Past Created Activity',
            'created_by' => $user->id,
            'starts_at' => $pastStart,
            'ends_at' => $pastEnd,
        ]);

        $user->interestedActivities()->attach($pastInterested->id);
        DB::table('activity_user')->insert([
            'activity_id' => $pastParticipating->id,
            'user_id' => $user->id,
            'is_absent' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $stats = app(UpcomingFeedQueryService::class)->upcomingActivityStatsForUser($user->id);

        $this->assertSame(0, $stats['interested']);
        $this->assertSame(0, $stats['participating']);
        $this->assertSame(0, $stats['created']);
    }

    public function test_upcoming_activity_stats_count_future_activities_per_category(): void
    {
        $user = User::factory()->create();
        $otherHost = User::factory()->create();
        $futureStart = now()->addDays(2);
        $futureEnd = now()->addDays(2)->addHours(3);

        $interestedActivity = Activity::factory()->create([
            'name' => 'Future Interested Activity',
            'created_by' => $otherHost->id,
            'starts_at' => $futureStart,
            'ends_at' => $futureEnd,
        ]);
        $participatingActivity = Activity::factory()->create([
            'name' => 'Future Participating Activity',
            'created_by' => $otherHost->id,
            'starts_at' => $futureStart->copy()->addHour(),
            'ends_at' => $futureEnd->copy()->addHour(),
        ]);
        $createdActivity = Activity::factory()->create([
            'name' => 'Future Created Activity',
            'created_by' => $user->id,
            'starts_at' => $futureStart->copy()->addHours(2),
            'ends_at' => $futureEnd->copy()->addHours(2),
        ]);

        $user->interestedActivities()->attach($interestedActivity->id);
        DB::table('activity_user')->insert([
            'activity_id' => $participatingActivity->id,
            'user_id' => $user->id,
            'is_absent' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $stats = app(UpcomingFeedQueryService::class)->upcomingActivityStatsForUser($user->id);

        $this->assertSame(1, $stats['interested']);
        $this->assertSame(1, $stats['participating']);
        $this->assertSame(1, $stats['created']);
    }

    public function test_upcoming_activity_stats_allow_overlap_across_categories(): void
    {
        $user = User::factory()->create();
        $futureStart = now()->addDays(4);
        $futureEnd = now()->addDays(4)->addHours(2);

        $activity = Activity::factory()->create([
            'name' => 'Overlapping Activity',
            'created_by' => $user->id,
            'starts_at' => $futureStart,
            'ends_at' => $futureEnd,
        ]);

        $user->interestedActivities()->attach($activity->id);
        DB::table('activity_user')->insert([
            'activity_id' => $activity->id,
            'user_id' => $user->id,
            'is_absent' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $stats = app(UpcomingFeedQueryService::class)->upcomingActivityStatsForUser($user->id);

        $this->assertSame(1, $stats['interested']);
        $this->assertSame(1, $stats['participating']);
        $this->assertSame(1, $stats['created']);
    }

    public function test_dashboard_renders_upcoming_activity_stats(): void
    {
        $user = User::factory()->create();
        $futureStart = now()->addDays(2);
        $futureEnd = now()->addDays(2)->addHours(3);

        $interestedActivity = Activity::factory()->create([
            'name' => 'Dashboard Stat Interested',
            'starts_at' => $futureStart,
            'ends_at' => $futureEnd,
        ]);
        $participatingActivity = Activity::factory()->create([
            'name' => 'Dashboard Stat Participating',
            'starts_at' => $futureStart->copy()->addHour(),
            'ends_at' => $futureEnd->copy()->addHour(),
        ]);
        Activity::factory()->create([
            'name' => 'Dashboard Stat Created',
            'created_by' => $user->id,
            'starts_at' => $futureStart->copy()->addHours(2),
            'ends_at' => $futureEnd->copy()->addHours(2),
        ]);

        $user->interestedActivities()->attach($interestedActivity->id);
        DB::table('activity_user')->insert([
            'activity_id' => $participatingActivity->id,
            'user_id' => $user->id,
            'is_absent' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Livewire::actingAs($user)
            ->test(Dashboard::class)
            ->assertViewHas('upcomingInterestedActivitiesCount', 1)
            ->assertViewHas('upcomingParticipatingActivitiesCount', 1)
            ->assertViewHas('upcomingCreatedActivitiesCount', 1)
            ->assertSeeHtml('data-ui="dashboard-activity-stats"');
    }

    public function test_dashboard_shows_zero_stats_when_feed_is_empty(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(Dashboard::class)
            ->assertViewHas('upcomingInterestedActivitiesCount', 0)
            ->assertViewHas('upcomingParticipatingActivitiesCount', 0)
            ->assertViewHas('upcomingCreatedActivitiesCount', 0)
            ->assertSee(__('ui.dashboard.empty'));
    }
}
