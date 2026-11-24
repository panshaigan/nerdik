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
     * @return list<array{label: string, slots: Collection<int, Slot>, starts_at: ?Carbon, boundary?: string}>
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

            return $slot->starts_at
                ->copy()
                ->setTimezone(display_timezone())
                ->format('Y-m-d H:i');
        })->sortKeys();

        $out = [];
        foreach ($grouped as $key => $groupSlots) {
            $out[] = [
                'label' => $key === '__no_time__'
                    ? __('ui.events.slots_group_no_time')
                    : format_datetime_in_user_tz($groupSlots->first()->starts_at, 'ddd, D MMM · HH:mm'),
                'slots' => $groupSlots->sortBy(fn (Slot $slot): int => (int) $slot->id)->values(),
                'starts_at' => $key === '__no_time__'
                    ? null
                    : $groupSlots->first()->starts_at,
            ];
        }

        $firstTimedSlot = $sorted->first(fn (Slot $s) => $s->starts_at !== null);
        $prependEventStart = $event->starts_at !== null
            && ($firstTimedSlot === null || ! $event->starts_at->equalTo($firstTimedSlot->starts_at));

        if ($prependEventStart) {
            array_unshift($out, [
                'label' => format_datetime_in_user_tz($event->starts_at, 'ddd, D MMM · HH:mm'),
                'slots' => collect(),
                'starts_at' => $event->starts_at,
                'boundary' => 'event_start',
            ]);
        }

        $lastSlot = $sorted->last();
        $lastSlotEnd = $lastSlot?->ends_at ?? $lastSlot?->starts_at;
        $appendEventEnd = $event->ends_at !== null
            && ($lastSlotEnd === null || ! $event->ends_at->equalTo($lastSlotEnd));

        if ($appendEventEnd) {
            $out[] = [
                'label' => format_datetime_in_user_tz($event->ends_at, 'ddd, D MMM · HH:mm'),
                'slots' => collect(),
                'starts_at' => $event->ends_at,
                'boundary' => 'event_end',
            ];
        }

        return $out;
    }

    /**
     * Display-timezone calendar date for the slot group whose start is nearest to now.
     * Used to expand every visible group on that day in the plan tab.
     *
     * @param  list<array{label: string, slots: Collection<int, Slot>, starts_at: ?Carbon, boundary?: string}>  $slotHourGroups
     */
    public function closestSlotDayForAutoOpen(array $slotHourGroups, bool $showEmptySlots, ?Carbon $now = null): ?string
    {
        $now ??= now();
        $timezone = display_timezone();
        $closestStartsAt = null;
        $closestDistance = null;

        foreach ($slotHourGroups as $group) {
            if (($group['boundary'] ?? null) !== null) {
                continue;
            }

            $startsAt = $group['starts_at'] ?? null;
            if ($startsAt === null) {
                continue;
            }

            $slots = $group['slots'];
            if (! $showEmptySlots) {
                $slots = $slots->filter(fn (Slot $slot): bool => $slot->activity !== null)->values();
            }

            if ($slots->isEmpty()) {
                continue;
            }

            $distance = $now->diffInSeconds($startsAt, absolute: true);
            if ($closestDistance === null || $distance < $closestDistance) {
                $closestDistance = $distance;
                $closestStartsAt = $startsAt;
            }
        }

        if ($closestStartsAt === null) {
            return null;
        }

        return $closestStartsAt->copy()->setTimezone($timezone)->toDateString();
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
                $activityIds = $event->relationLoaded('slots')
                    ? $event->slots
                        ->pluck('activity_id')
                        ->filter(fn ($id) => $id !== null)
                        ->map(fn ($id) => (int) $id)
                        ->unique()
                        ->values()
                    : Slot::query()
                        ->where('event_id', $event->id)
                        ->whereNotNull('activity_id')
                        ->pluck('activity_id')
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
