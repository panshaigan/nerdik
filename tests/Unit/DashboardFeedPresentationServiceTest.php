<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Dashboard\DashboardFeedPresentationService;
use Carbon\Carbon;
use Tests\TestCase;

class DashboardFeedPresentationServiceTest extends TestCase
{
    public function test_month_groups_for_feed_items_places_missing_start_times_in_no_time_bucket(): void
    {
        $service = new DashboardFeedPresentationService;

        $groups = $service->monthGroupsForFeedItems(collect([
            [
                'kind' => 'event',
                'event' => (object) ['id' => 1],
                'starts_at' => null,
            ],
        ]));

        $this->assertCount(1, $groups);
        $this->assertSame(__('ui.events.slots_group_no_time'), $groups[0]['label']);
        $this->assertNull($groups[0]['starts_at']);
        $this->assertCount(1, $groups[0]['items']);
    }

    public function test_month_groups_for_feed_items_groups_same_month_items_together(): void
    {
        $service = new DashboardFeedPresentationService;
        $startsAt = Carbon::parse('2026-06-12 14:30:00', 'UTC');

        $groups = $service->monthGroupsForFeedItems(collect([
            [
                'kind' => 'event',
                'event' => (object) ['id' => 1],
                'starts_at' => $startsAt,
            ],
            [
                'kind' => 'activity',
                'activity' => (object) ['id' => 2],
                'starts_at' => $startsAt->copy()->addDays(5)->setTime(10, 0),
            ],
        ]));

        $this->assertCount(1, $groups);
        $this->assertSame(
            format_datetime_in_user_tz($startsAt, 'MMMM YYYY'),
            $groups[0]['label'],
        );
        $this->assertCount(2, $groups[0]['items']);
    }

    public function test_month_groups_for_feed_items_separates_different_months(): void
    {
        $service = new DashboardFeedPresentationService;
        $juneStart = Carbon::parse('2026-06-12 14:30:00', 'UTC');
        $julyStart = Carbon::parse('2026-07-03 10:00:00', 'UTC');

        $groups = $service->monthGroupsForFeedItems(collect([
            [
                'kind' => 'event',
                'event' => (object) ['id' => 1],
                'starts_at' => $juneStart,
            ],
            [
                'kind' => 'activity',
                'activity' => (object) ['id' => 2],
                'starts_at' => $julyStart,
            ],
        ]));

        $this->assertCount(2, $groups);
        $this->assertSame(
            format_datetime_in_user_tz($juneStart, 'MMMM YYYY'),
            $groups[0]['label'],
        );
        $this->assertSame(
            format_datetime_in_user_tz($julyStart, 'MMMM YYYY'),
            $groups[1]['label'],
        );
    }
}
