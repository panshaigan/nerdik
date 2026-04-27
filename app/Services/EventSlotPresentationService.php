<?php

namespace App\Services;

use App\Models\ActivityUser;
use App\Models\Event;
use App\Models\Slot;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Read-side grouping and enrollment aggregates for the event show page.
 */
class EventSlotPresentationService
{
    /**
     * @return list<array{label: string, slots: Collection<int, Slot>, boundary?: string}>
     */
    public function slotHourGroupsForEvent(Event $event): array
    {
        $sorted = $event->slots
            ->sortBy(fn (Slot $s) => $s->starts_at?->getTimestamp() ?? PHP_INT_MAX)
            ->values();

        $grouped = $sorted->groupBy(function (Slot $slot) {
            if (! $slot->starts_at) {
                return '__no_time__';
            }

            return format_in_user_tz($slot->starts_at, 'Y-m-d H');
        })->sortKeys();

        $out = [];
        foreach ($grouped as $key => $groupSlots) {
            $out[] = [
                'label' => $key === '__no_time__'
                    ? __('ui.events.slots_group_no_time')
                    : format_datetime_in_user_tz($groupSlots->first()->starts_at, 'ddd, D MMM · HH:00'),
                'slots' => $groupSlots,
            ];
        }

        $firstTimedSlot = $sorted->first(fn (Slot $s) => $s->starts_at !== null);
        $prependEventStart = true;
//        if ($event->starts_at) {
//            if ($firstTimedSlot === null) {
//                $prependEventStart = true;
//            } elseif ($event->starts_at->lt($firstTimedSlot->starts_at)) {
//                $eventHour = format_in_user_tz($event->starts_at, 'Y-m-d H');
//                $firstHour = format_in_user_tz($firstTimedSlot->starts_at, 'Y-m-d H');
//                $prependEventStart = $eventHour !== $firstHour;
//            }
//        }

        if ($prependEventStart) {
            array_unshift($out, [
                'label' => format_datetime_in_user_tz($event->starts_at, 'ddd, D MMM · HH:00'),
                'slots' => collect(),
                'boundary' => 'event_start',
            ]);
        }

        $lastSlot = $sorted->last();
        $lastSlotEnd = $lastSlot?->ends_at ?? $lastSlot?->starts_at;
        $appendEventEnd = $event->ends_at !== null
            && ($lastSlotEnd === null || ! $event->ends_at->equalTo($lastSlotEnd));

        if ($appendEventEnd) {
            $out[] = [
                'label' => format_datetime_in_user_tz($event->ends_at, 'ddd, D MMM · HH:00'),
                'slots' => collect(),
                'boundary' => 'event_end',
            ];
        }

        return $out;
    }

    public function enrollmentPresentation(Event $event, Carbon $now): EventEnrollmentPresentationData
    {
        $activeEnrollmentWindow = $event->enrollmentWindows->first(function ($w) use ($now) {
            return $w->starts_at !== null
                && $w->ends_at !== null
                && $now->between($w->starts_at, $w->ends_at);
        });

        $activeWindowRemainingByActivityId = [];
        if ($activeEnrollmentWindow !== null) {
            $perActivityMax = $activeEnrollmentWindow->maxAllowedParticipantsPerActivityEffective();
            if ($perActivityMax !== null) {
                $activityIds = $event->slots
                    ->pluck('activity_id')
                    ->filter(fn ($id) => $id !== null)
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->values();

                if ($activityIds->isNotEmpty()) {
                    $taken = ActivityUser::query()
                        ->selectRaw('activity_id, COUNT(*) as aggregate')
                        ->whereIn('activity_id', $activityIds->all())
                        ->whereBetween('created_at', [$activeEnrollmentWindow->starts_at, $activeEnrollmentWindow->ends_at])
                        ->groupBy('activity_id')
                        ->pluck('aggregate', 'activity_id');

                    foreach ($activityIds as $activityId) {
                        $activeWindowRemainingByActivityId[(int) $activityId] = max(0, $perActivityMax - (int) ($taken[(int) $activityId] ?? 0));
                    }
                }
            }
        }

        return new EventEnrollmentPresentationData($activeEnrollmentWindow, $activeWindowRemainingByActivityId);
    }
}
