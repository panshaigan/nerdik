<?php

declare(strict_types=1);

namespace App\Support\Ui;

use App\Models\Activity;
use Carbon\CarbonInterface;

final class ActivityPreviewAboutPresenter
{
    public function build(Activity $activity): ActivityPreviewAboutViewData
    {
        $activity->loadMissing([
            'slot.place.parent',
            'place.parent',
        ]);

        $selfHosted = (int) $activity->hosting_mode === Activity::HOSTING_MODE_SELF_HOSTED;
        $slot = $activity->slot;
        $slotPlace = $slot?->place;
        $schedulePlace = $selfHosted ? $activity->place : ($slotPlace ?? $activity->place);

        $startsAt = $selfHosted
            ? $activity->starts_at
            : ($slot?->starts_at ?? $activity->starts_at);
        $endsAt = $selfHosted
            ? $activity->ends_at
            : ($slot?->ends_at ?? $activity->ends_at);

        $useSlotClockFormat = $slot !== null
            && ! $selfHosted
            && ($slot->starts_at !== null || $slot->ends_at !== null);

        return new ActivityPreviewAboutViewData(
            slotName: (! $selfHosted && filled($slot?->name)) ? (string) $slot->name : null,
            timeLabel: $this->formatTimeLabel($startsAt, $endsAt, $useSlotClockFormat),
            locationLabel: $schedulePlace !== null ? $schedulePlace->venueRoomLabel() : '',
        );
    }

    private function formatTimeLabel(mixed $startsAt, mixed $endsAt, bool $useSlotClockFormat): string
    {
        if ($startsAt === null && $endsAt === null) {
            return '';
        }

        if ($useSlotClockFormat) {
            return $this->formatClockRange($startsAt, $endsAt);
        }

        if ($startsAt !== null && $endsAt !== null) {
            return format_datetime_range_compact($startsAt, $endsAt);
        }

        if ($startsAt !== null) {
            return format_in_user_tz($startsAt, 'D, M j · H:i');
        }

        return format_in_user_tz($endsAt, 'D, M j · H:i');
    }

    private function formatClockRange(mixed $startsAt, mixed $endsAt): string
    {
        if ($startsAt instanceof CarbonInterface && $endsAt instanceof CarbonInterface) {
            return format_in_user_tz($startsAt, 'H:i').' – '.format_in_user_tz($endsAt, 'H:i');
        }

        if ($startsAt instanceof CarbonInterface) {
            return format_in_user_tz($startsAt, 'H:i');
        }

        if ($endsAt instanceof CarbonInterface) {
            return format_in_user_tz($endsAt, 'H:i');
        }

        return '';
    }
}
