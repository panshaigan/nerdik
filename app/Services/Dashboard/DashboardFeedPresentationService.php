<?php

namespace App\Services\Dashboard;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Month-bucket grouping for the dashboard upcoming feed.
 */
class DashboardFeedPresentationService
{
    /**
     * @param  Collection<int, array{kind: string, event?: mixed, activity?: mixed, starts_at: ?Carbon}>  $feedItems
     * @return list<array{label: string, items: Collection<int, array{kind: string, event?: mixed, activity?: mixed, starts_at: ?Carbon}>, starts_at: ?Carbon}>
     */
    public function monthGroupsForFeedItems(Collection $feedItems): array
    {
        $sorted = $feedItems
            ->sortBy(fn (array $item) => $item['starts_at']?->getTimestamp() ?? PHP_INT_MAX)
            ->values();

        $grouped = $sorted->groupBy(function (array $item): string {
            $startsAt = $item['starts_at'] ?? null;

            if ($startsAt === null) {
                return '__no_time__';
            }

            return $this->monthBucketKey($startsAt);
        })->sortKeys();

        $out = [];
        foreach ($grouped as $key => $groupItems) {
            $firstStartsAt = $key === '__no_time__'
                ? null
                : $groupItems->first()['starts_at'] ?? null;

            $out[] = [
                'label' => $key === '__no_time__'
                    ? __('ui.events.slots_group_no_time')
                    : $this->formatMonthLabel($firstStartsAt),
                'items' => $groupItems->values(),
                'starts_at' => $firstStartsAt,
            ];
        }

        return $out;
    }

    private function monthBucketKey(CarbonInterface $startsAt): string
    {
        $carbon = $startsAt->copy()
            ->setTimezone(display_timezone())
            ->locale(app()->getLocale());

        return $carbon->format('Y-m');
    }

    private function formatMonthLabel(?CarbonInterface $startsAt): string
    {
        if ($startsAt === null) {
            return '';
        }

        return format_datetime_in_user_tz($startsAt, 'MMMM YYYY');
    }
}
