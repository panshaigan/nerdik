<?php

declare(strict_types=1);

namespace App\Support\Browse;

use App\Models\Activity;
use Illuminate\Database\Eloquent\Builder;

/**
 * Overlap filter: listing schedule intersects the inclusive date range [from, to].
 */
final class BrowseDateRangeOverlap
{
    public static function applyToEventQuery(Builder $query, ?string $fromDate, ?string $toDate): void
    {
        if (! filled($fromDate) && ! filled($toDate)) {
            return;
        }

        self::applyOverlap(
            $query,
            'events.starts_at',
            'COALESCE(events.ends_at, events.starts_at)',
            $fromDate,
            $toDate,
        );
    }

    public static function applyToActivityQuery(Builder $query, ?string $fromDate, ?string $toDate): void
    {
        if (! filled($fromDate) && ! filled($toDate)) {
            return;
        }

        $query->where(function (Builder $outer) use ($fromDate, $toDate): void {
            $outer->where(function (Builder $selfHosted) use ($fromDate, $toDate): void {
                $selfHosted->where('activities.hosting_mode', Activity::HOSTING_MODE_SELF_HOSTED);
                self::applyOverlap(
                    $selfHosted,
                    'activities.starts_at',
                    'COALESCE(activities.ends_at, activities.starts_at)',
                    $fromDate,
                    $toDate,
                );
            })->orWhere(function (Builder $scheduled) use ($fromDate, $toDate): void {
                $scheduled->where('activities.hosting_mode', Activity::HOSTING_MODE_SCHEDULED_ON_EVENT)
                    ->whereHas('slot', function (Builder $slotQ) use ($fromDate, $toDate): void {
                        self::applyOverlap(
                            $slotQ,
                            'slots.starts_at',
                            'COALESCE(slots.ends_at, slots.starts_at)',
                            $fromDate,
                            $toDate,
                        );
                    });
            });
        });
    }

    private static function applyOverlap(
        Builder $query,
        string $startColumn,
        string $endExpression,
        ?string $fromDate,
        ?string $toDate,
    ): void {
        if (filled($toDate)) {
            $query->whereRaw("DATE({$startColumn}) <= ?", [$toDate]);
        }

        if (filled($fromDate)) {
            $query->whereRaw("DATE({$endExpression}) >= ?", [$fromDate]);
        }
    }
}
