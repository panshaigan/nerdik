<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Group;
use App\Models\User;
use App\Models\UserAvailabilityWeekday;
use App\Services\GroupAvailabilityService;
use App\Services\GroupMembershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

final class GroupAvailabilityServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_month_summary_counts_available_members(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $group = app(GroupMembershipService::class)->create($owner, 'Tabletop');

        UserAvailabilityWeekday::query()->create([
            'user_id' => $owner->id,
            'sat' => true,
        ]);
        UserAvailabilityWeekday::query()->create([
            'user_id' => $member->id,
            'sat' => true,
        ]);
        $group->users()->attach($member->id);

        $service = app(GroupAvailabilityService::class);
        $summary = $service->monthSummary($group, Carbon::parse('2026-03-01'));

        $this->assertSame(2, $summary['days']['2026-03-07']['available']);
        $this->assertTrue($summary['days']['2026-03-07']['matches_filter']);

        $summaryFiltered = $service->monthSummary($group, Carbon::parse('2026-03-01'), minAvailable: 3);
        $this->assertFalse($summaryFiltered['days']['2026-03-07']['matches_filter']);
    }

    public function test_day_detail_lists_members_by_status(): void
    {
        $owner = User::factory()->create();
        $availableUser = User::factory()->create();
        $group = Group::factory()->withOwnerMember()->create(['created_by' => $owner->id]);
        $group->users()->syncWithoutDetaching([$owner->id, $availableUser->id]);

        UserAvailabilityWeekday::query()->create([
            'user_id' => $owner->id,
            'mon' => false,
        ]);
        UserAvailabilityWeekday::query()->create([
            'user_id' => $availableUser->id,
            'mon' => true,
        ]);

        $detail = app(GroupAvailabilityService::class)->dayDetail($group, Carbon::parse('2026-03-02'));

        $this->assertCount(1, $detail['available']);
        $this->assertCount(1, $detail['unavailable']);
        $this->assertSame((int) $availableUser->id, (int) $detail['available']->first()?->id);
    }
}
