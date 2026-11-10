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

    /**
     * @param  Collection<int, array{kind: string, event?: mixed, activity?: mixed, starts_at: ?Carbon}>  $feedItems
     * @param  Collection<int, array{kind: string, event?: mixed, activity?: mixed, starts_at: ?Carbon}>  $exclude
     * @return Collection<int, array{kind: string, event?: mixed, activity?: mixed, starts_at: ?Carbon}>
     */
    public function excludeFromFeed(Collection $feedItems, Collection $exclude): Collection
    {
        if ($exclude->isEmpty()) {
            return $feedItems->values();
        }

        $excludeKeys = $exclude
            ->map(fn (array $item): string => $this->listingKey($item))
            ->flip();

        return $feedItems
            ->reject(fn (array $item): bool => $excludeKeys->has($this->listingKey($item)))
            ->values();
    }

    /**
     * @param  array{kind: string, event?: mixed, activity?: mixed, starts_at: ?Carbon}  $item
     */
    public function listingKey(array $item): string
    {
        $id = match ($item['kind'] ?? '') {
            'event' => (int) ($item['event']?->id ?? 0),
            'activity' => (int) ($item['activity']?->id ?? 0),
            default => 0,
        };

        return ($item['kind'] ?? 'unknown').':'.$id;
    }
}
