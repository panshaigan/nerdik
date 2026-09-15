<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Enums\ParticipationMode;
use App\Models\Activity;
use App\Models\ActivityWaitlistEntry;
use App\Models\User;
use App\Services\ActivityLotteryService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ResolveActivityLotteriesOptimizationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_due_activities_only_includes_unresolved_lottery_mode_activities(): void
    {
        Notification::fake();

        $host = User::factory()->create();
        $openActivity = Activity::factory()->create([
            'created_by' => $host->id,
            'updated_by' => $host->id,
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'participation_mode' => ParticipationMode::Open,
            'max_participants' => 2,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHours(2),
        ]);
        $resolvedLottery = Activity::factory()->create([
            'created_by' => $host->id,
            'updated_by' => $host->id,
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'participation_mode' => ParticipationMode::Lottery,
            'lottery_draw_in_hours' => 1,
            'lottery_resolved_at' => now()->subHour(),
            'max_participants' => 2,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHours(2),
        ]);
        $pendingLottery = Activity::factory()->create([
            'created_by' => $host->id,
            'updated_by' => $host->id,
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'participation_mode' => ParticipationMode::Lottery,
            'lottery_draw_in_hours' => 1,
            'lottery_resolved_at' => null,
            'max_participants' => 2,
            'starts_at' => now()->addHours(2),
            'ends_at' => now()->addHours(4),
        ]);

        $waitlistUser = User::factory()->create();
        ActivityWaitlistEntry::query()->create([
            'activity_id' => $pendingLottery->id,
            'user_id' => $waitlistUser->id,
            'position' => 1,
        ]);

        Carbon::setTestNow(now()->addHour()->addMinute());

        $dueIds = app(ActivityLotteryService::class)
            ->dueActivities()
            ->pluck('id')
            ->all();

        $this->assertSame([$pendingLottery->id], $dueIds);
        $this->assertNotContains($openActivity->id, $dueIds);
        $this->assertNotContains($resolvedLottery->id, $dueIds);
    }
}
