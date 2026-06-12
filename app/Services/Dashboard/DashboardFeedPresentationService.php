<?php

namespace App\Services\Dashboard;

use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Hour-bucket grouping for the dashboard upcoming feed.
 */
class DashboardFeedPresentationService
{
    /**
     * @param  Collection<int, array{kind: string, event?: mixed, activity?: mixed, starts_at: ?Carbon}>  $feedItems
     * @return list<array{label: string, items: Collection<int, array{kind: string, event?: mixed, activity?: mixed, starts_at: ?Carbon}>, starts_at: ?Carbon}>
     */
    public function hourGroupsForFeedItems(Collection $feedItems): array
    {
        $sorted = $feedItems
            ->sortBy(fn (array $item) => $item['starts_at']?->getTimestamp() ?? PHP_INT_MAX)
            ->values();

        $grouped = $sorted->groupBy(function (array $item): string {
            $startsAt = $item['starts_at'] ?? null;

            if ($startsAt === null) {
                return '__no_time__';
            }

            return format_in_user_tz($startsAt, 'Y-m-d H');
        })->sortKeys();

        $out = [];
        foreach ($grouped as $key => $groupItems) {
            $firstStartsAt = $key === '__no_time__'
                ? null
                : $groupItems->first()['starts_at'] ?? null;

            $out[] = [
                'label' => $key === '__no_time__'
                    ? __('ui.events.slots_group_no_time')
                    : format_datetime_in_user_tz($firstStartsAt, 'ddd, D MMM · HH:00'),
                'items' => $groupItems->values(),
                'starts_at' => $firstStartsAt,
            ];
        }

        return $out;
    }
}
