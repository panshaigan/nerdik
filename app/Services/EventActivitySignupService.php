<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\ActivityUser;
use App\Models\Event;
use App\Models\EventEnrollmentWindow;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class EventActivitySignupService
{
    /**
     * Scheduled real-time window: slot start through slot start + activity duration (or slot ends_at if no duration).
     *
     * @return array{0: Carbon, 1: Carbon}|null
     */
    public function activityScheduledWindow(Activity $activity): ?array
    {
        if ($activity->hosting_mode === Activity::HOSTING_MODE_SELF_HOSTED) {
            if ($activity->starts_at === null) {
                return null;
            }
            $start = $activity->starts_at->copy();
            $end = $activity->ends_at?->copy() ?? $start->copy();
            if ($end->lt($start)) {
                $end = $start->copy();
            }

            return [$start, $end];
        }

        $slot = $activity->slot;
        if ($slot === null || $slot->starts_at === null) {
            return null;
        }

        $start = $slot->starts_at->copy();

        if ($activity->duration_in_minutes !== null && (int) $activity->duration_in_minutes > 0) {
            $end = $start->copy()->addMinutes((int) $activity->duration_in_minutes);
        } elseif ($slot->ends_at !== null) {
            $end = $slot->ends_at->copy();
        } else {
            $end = $start->copy();
        }

        if ($end->lt($start)) {
            $end = $start->copy();
        }

        return [$start, $end];
    }

    /**
     * First matching window in case boundaries touch adjacent periods.
     */
    public function firstPeriodContaining(Event $event, Carbon $moment): ?EventEnrollmentWindow
    {
        foreach ($event->enrollmentWindows->sortBy('starts_at') as $period) {
            if ($moment->between($period->starts_at, $period->ends_at)) {
                return $period;
            }
        }

        return null;
    }

    /**
     * How many activities this user has joined on this event with participant created_at inside the period.
     */
    public function userSignupCountDuringPeriod(Event $event, User $user, EventEnrollmentWindow $period): int
    {
        return (int) ActivityUser::query()
            ->where('user_id', $user->id)
            ->whereBetween('created_at', [$period->starts_at, $period->ends_at])
            ->whereHas('activity.slot', function ($q) use ($event) {
                $q->where('event_id', $event->id);
            })
            ->count();
    }

    public function schedulesOverlap(Activity $a, Activity $b): bool
    {
        $wa = $this->activityScheduledWindow($a);
        $wb = $this->activityScheduledWindow($b);
        if ($wa === null || $wb === null) {
            return false;
        }

        [$a0, $a1] = $wa;
        [$b0, $b1] = $wb;

        return $a0->lt($b1) && $b0->lt($a1);
    }

    /**
     * Other activities this user already participates in whose scheduled time overlaps {@see $activity} (any event).
     *
     * @return Collection<int, Activity>
     */
    public function overlappingParticipatingActivities(Activity $activity, User $user): Collection
    {
        $otherIds = ActivityUser::query()
            ->where('user_id', $user->id)
            ->where('activity_id', '!=', $activity->id)
            ->pluck('activity_id')
            ->all();

        if ($otherIds === []) {
            return collect();
        }

        $others = Activity::query()
            ->whereIn('id', $otherIds)
            ->with(['slot.event'])
            ->get();

        return $others
            ->filter(fn (Activity $other) => $this->schedulesOverlap($activity, $other))
            ->values();
    }

    /**
     * Human-readable labels for overlapping activities (name, with event when available).
     *
     * @param  Collection<int, Activity>  $activities
     */
    public function formatOverlappingActivityLabels(Collection $activities): string
    {
        return $activities
            ->map(function (Activity $a) {
                $name = $a->name;
                $eventName = $a->slot?->event?->name;

                return $eventName
                    ? $name.' ('.$eventName.')'
                    : $name;
            })
            ->implode(', ');
    }

    /**
     * @throws ValidationException
     */
    public function assertCanSignup(Activity $activity, User $user, bool $hostApprovingParticipant = false): void
    {
        $activity->loadMissing('slot.event.enrollmentWindows');

        $slot = $activity->slot;
        if ($slot === null || $slot->event_id === null) {
            return;
        }

        $event = $slot->event;
        if ($event === null) {
            return;
        }

        $now = Carbon::now();

        $periods = $event->enrollmentWindows;
        if ($periods->isEmpty()) {
            $this->assertNoScheduleOverlap($activity, $user, $hostApprovingParticipant);

            return;
        }

        $active = $this->firstPeriodContaining($event, $now);
        if ($active === null) {
            throw ValidationException::withMessages([
                '_' => [__('ui.events.enrollment_outside_window')],
            ]);
        }

        $max = $active->maxActivitiesPerUserEffective();
        if ($max !== null) {
            $count = $this->userSignupCountDuringPeriod($event, $user, $active);
            if ($count >= $max) {
                throw ValidationException::withMessages([
                    '_' => [__('ui.events.enrollment_window_limit_reached', ['max' => $max])],
                ]);
            }
        }

        $this->assertNoScheduleOverlap($activity, $user, $hostApprovingParticipant);
    }

    /**
     * @throws ValidationException
     */
    protected function assertNoScheduleOverlap(Activity $activity, User $user, bool $hostApprovingParticipant = false): void
    {
        $overlapping = $this->overlappingParticipatingActivities($activity, $user);
        if ($overlapping->isEmpty()) {
            return;
        }

        $list = $this->formatOverlappingActivityLabels($overlapping);

        $message = $hostApprovingParticipant
            ? __('ui.events.enrollment_schedule_overlap_host', ['list' => $list])
            : __('ui.events.enrollment_schedule_overlap', ['list' => $list]);

        throw ValidationException::withMessages([
            '_' => [$message],
        ]);
    }

    /**
     * Validate period rows for an event form (non-overlapping, end on/before event end, may start before event).
     *
     * @param  list<array{starts_at: string, ends_at: string, max_activities_per_user?: mixed}>  $rows
     * @return list<array{starts_at: Carbon, ends_at: Carbon, max_activities_per_user: int|null}>
     */
    public function validateAndNormalizePeriodRowsForEvent(Event $event, array $rows, Carbon $nowUtc): array
    {
        $eventStarts = $event->starts_at;
        $eventEnds = $event->ends_at;
        if ($eventStarts === null || $eventEnds === null) {
            throw ValidationException::withMessages([
                'enrollment_windows' => [__('ui.events.enrollment_windows_need_event_dates')],
            ]);
        }

        $normalized = [];
        foreach ($rows as $i => $row) {
            $s = isset($row['starts_at']) ? trim((string) $row['starts_at']) : '';
            $e = isset($row['ends_at']) ? trim((string) $row['ends_at']) : '';
            if ($s === '' && $e === '') {
                continue;
            }
            if ($s === '' || $e === '') {
                throw ValidationException::withMessages([
                    "enrollment_windows.{$i}" => [__('ui.events.enrollment_window_incomplete')],
                ]);
            }

            $start = parse_datetime_to_utc($s);
            $end = parse_datetime_to_utc($e);
            if ($start === null || $end === null) {
                throw ValidationException::withMessages([
                    "enrollment_windows.{$i}" => [__('ui.events.enrollment_window_invalid_datetime')],
                ]);
            }

            if ($end->lte($start)) {
                throw ValidationException::withMessages([
                    "enrollment_windows.{$i}" => [__('ui.events.enrollment_window_end_after_start')],
                ]);
            }

            // Signup may open before the event starts; it must end by the event end (inclusive).
            if ($end->gt($eventEnds)) {
                throw ValidationException::withMessages([
                    "enrollment_windows.{$i}" => [__('ui.events.enrollment_window_end_before_event_end')],
                ]);
            }

            if ($end->lt($nowUtc)) {
                throw ValidationException::withMessages([
                    "enrollment_windows.{$i}" => [__('ui.events.enrollment_window_end_not_past')],
                ]);
            }

            $maxRaw = $row['max_activities_per_user'] ?? null;
            $max = null;
            if ($maxRaw !== null && $maxRaw !== '') {
                $max = (int) $maxRaw;
                if ($max < 0) {
                    throw ValidationException::withMessages([
                        "enrollment_windows.{$i}.max_activities_per_user" => [__('ui.events.enrollment_window_max_invalid')],
                    ]);
                }
                if ($max === 0) {
                    $max = null;
                }
            }

            $normalized[] = [
                'starts_at' => $start,
                'ends_at' => $end,
                'max_activities_per_user' => $max,
            ];
        }

        if ($normalized === []) {
            return [];
        }

        usort($normalized, fn ($a, $b) => $a['starts_at'] <=> $b['starts_at']);

        for ($i = 0; $i < count($normalized) - 1; $i++) {
            $a = $normalized[$i];
            $b = $normalized[$i + 1];
            if ($a['ends_at']->gt($b['starts_at'])) {
                throw ValidationException::withMessages([
                    'enrollment_windows' => [__('ui.events.enrollment_windows_must_not_overlap')],
                ]);
            }
        }

        return $normalized;
    }
}
