<?php

namespace App\Services\Dashboard;

use Carbon\Carbon;
use Carbon\CarbonInterface;
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

            return $this->hourBucketKey($startsAt);
        })->sortKeys();

        $out = [];
        foreach ($grouped as $key => $groupItems) {
            $firstStartsAt = $key === '__no_time__'
                ? null
                : $groupItems->first()['starts_at'] ?? null;

            $out[] = [
                'label' => $key === '__no_time__'
                    ? __('ui.events.slots_group_no_time')
                    : $this->formatHourLabel($firstStartsAt),
                'items' => $groupItems->values(),
                'starts_at' => $firstStartsAt,
            ];
        }

        return $out;
    }

    private function hourBucketKey(CarbonInterface $startsAt): string
    {
        $carbon = $startsAt->copy()
            ->setTimezone(display_timezone())
            ->locale(app()->getLocale());

        return $carbon->format('Y-m-d H');
    }

    private function formatHourLabel(?CarbonInterface $startsAt): string
    {
        if ($startsAt === null) {
            return '';
        }

        $carbon = $startsAt->copy()
            ->setTimezone(display_timezone())
            ->locale(app()->getLocale());

        return $carbon->translatedFormat('D, j M').' · '.format_time_in_user_tz($carbon->copy()->startOfHour());
    }
}
