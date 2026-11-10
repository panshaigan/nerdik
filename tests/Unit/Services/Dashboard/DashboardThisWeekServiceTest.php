<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Dashboard;

use App\Services\Dashboard\DashboardThisWeekService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class DashboardThisWeekServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_items_for_this_week_returns_only_items_within_current_week(): void
    {
        $timezone = display_timezone();
        $now = now()->setTimezone($timezone);
        $inWeek = $now->copy()->addDay();
        $nextMonth = $now->copy()->addMonth();

        $service = new DashboardThisWeekService;

        $items = $service->itemsForThisWeek(collect([
            ['kind' => 'activity', 'starts_at' => $inWeek],
            ['kind' => 'event', 'starts_at' => $nextMonth],
        ]));

        $this->assertCount(1, $items);
        $this->assertSame('activity', $items->first()['kind']);
    }

    public function test_items_for_this_week_respects_limit(): void
    {
        $timezone = display_timezone();
        $now = now()->setTimezone($timezone);

        $feed = Collection::times(5, fn (int $i) => [
            'kind' => 'activity',
            'starts_at' => $now->copy()->addDays($i),
        ]);

        $service = new DashboardThisWeekService;
        $items = $service->itemsForThisWeek($feed, 3);

        $this->assertCount(3, $items);
    }
}
