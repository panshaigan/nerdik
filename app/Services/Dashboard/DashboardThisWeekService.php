<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use Carbon\Carbon;
use Illuminate\Support\Collection;

class DashboardThisWeekService
{
    /**
     * @param  Collection<int, array{kind: string, event?: mixed, activity?: mixed, starts_at: ?Carbon}>  $feedItems
     * @return Collection<int, array{kind: string, event?: mixed, activity?: mixed, starts_at: ?Carbon}>
     */
    public function itemsForThisWeek(Collection $feedItems, int $limit = 3): Collection
    {
        $timezone = display_timezone();
        $now = now()->setTimezone($timezone);
        $weekEnd = $now->copy()->endOfWeek();

        return $feedItems
            ->filter(function (array $item) use ($now, $weekEnd, $timezone): bool {
                $startsAt = $item['starts_at'] ?? null;
                if ($startsAt === null) {
                    return false;
                }

                $local = $startsAt->copy()->setTimezone($timezone);

                return $local->greaterThanOrEqualTo($now) && $local->lessThanOrEqualTo($weekEnd);
            })
            ->take($limit)
            ->values();
    }
}
