<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\ActivityParticipant;
use App\Models\Event;
use App\Models\EventSignupPeriod;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class EventActivitySignupService
{
    /**
     * Scheduled real-time window: slot start through slot start + activity duration (or slot ends_at if no duration).
     *
     * @return array{0: \Carbon\Carbon, 1: \Carbon\Carbon}|null
     */
    public function activityScheduledWindow(Activity $activity): ?array
    {
        $slot = $activity->slot;
        if ($slot === null || $slot->starts_at === null) {
            return null;
        }

        $start = $slot->starts_at->copy();

        if ($activity->duration_minutes !== null && (int) $activity->duration_minutes > 0) {
            $end = $start->copy()->addMinutes((int) $activity->duration_minutes);
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
    public function firstPeriodContaining(Event $event, Carbon $moment): ?EventSignupPeriod
    {
        foreach ($event->signupPeriods->sortBy('starts_at') as $period) {
            if ($moment->between($period->starts_at, $period->ends_at)) {
                return $period;
            }
        }

        return null;
    }

    /**
     * How many activities this user has joined on this event with participant created_at inside the period.
     */
    public function userSignupCountDuringPeriod(Event $event, User $user, EventSignupPeriod $period): int
    {
        return (int) ActivityParticipant::query()
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
     * Whether the user already participates in another activity on the same event whose schedule overlaps this activity.
     */
    public function hasOverlappingParticipationOnEvent(Activity $activity, User $user): bool
    {
        $slot = $activity->slot;
        if ($slot === null || $slot->event_id === null) {
            return false;
        }

        $eventId = (int) $slot->event_id;

        $otherIds = ActivityParticipant::query()
            ->where('user_id', $user->id)
            ->where('activity_id', '!=', $activity->id)
            ->pluck('activity_id')
            ->all();

        if ($otherIds === []) {
            return false;
        }

        $others = Activity::query()
            ->whereIn('id', $otherIds)
            ->whereHas('slot', fn ($q) => $q->where('event_id', $eventId))
            ->with('slot')
            ->get();

        foreach ($others as $other) {
            if ($this->schedulesOverlap($activity, $other)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @throws ValidationException
     */
    public function assertCanSignup(Activity $activity, User $user): void
    {
        $activity->loadMissing('slot.event.signupPeriods');

        $slot = $activity->slot;
        if ($slot === null || $slot->event_id === null) {
            return;
        }

        $event = $slot->event;
        if ($event === null) {
            return;
        }

        $now = Carbon::now();

        $periods = $event->signupPeriods;
        if ($periods->isEmpty()) {
            $this->assertNoScheduleOverlap($activity, $user);

            return;
        }

        $active = $this->firstPeriodContaining($event, $now);
        if ($active === null) {
            throw ValidationException::withMessages([
                '_' => [__('ui.events.signup_outside_period')],
            ]);
        }

        $max = $active->maxActivitiesEffective();
        if ($max !== null) {
            $count = $this->userSignupCountDuringPeriod($event, $user, $active);
            if ($count >= $max) {
                throw ValidationException::withMessages([
                    '_' => [__('ui.events.signup_period_limit_reached', ['max' => $max])],
                ]);
            }
        }

        $this->assertNoScheduleOverlap($activity, $user);
    }

    /**
     * @throws ValidationException
     */
    protected function assertNoScheduleOverlap(Activity $activity, User $user): void
    {
        if ($this->hasOverlappingParticipationOnEvent($activity, $user)) {
            throw ValidationException::withMessages([
                '_' => [__('ui.events.signup_schedule_overlap')],
            ]);
        }
    }

    /**
     * Validate period rows for an event form (non-overlapping, end on/before event end, may start before event).
     *
     * @param  list<array{starts_at: string, ends_at: string, max_activities?: mixed}>  $rows
     * @return list<array{starts_at: \Carbon\Carbon, ends_at: \Carbon\Carbon, max_activities: int|null}>
     */
    public function validateAndNormalizePeriodRowsForEvent(Event $event, array $rows, Carbon $nowUtc): array
    {
        $eventStarts = $event->starts_at;
        $eventEnds = $event->ends_at;
        if ($eventStarts === null || $eventEnds === null) {
            throw ValidationException::withMessages([
                'signup_periods' => [__('ui.events.signup_periods_need_event_dates')],
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
                    "signup_periods.{$i}" => [__('ui.events.signup_period_incomplete')],
                ]);
            }

            $start = parse_datetime_to_utc($s);
            $end = parse_datetime_to_utc($e);
            if ($start === null || $end === null) {
                throw ValidationException::withMessages([
                    "signup_periods.{$i}" => [__('ui.events.signup_period_invalid_datetime')],
                ]);
            }

            if ($end->lte($start)) {
                throw ValidationException::withMessages([
                    "signup_periods.{$i}" => [__('ui.events.signup_period_end_after_start')],
                ]);
            }

            // Signup may open before the event starts; it must end by the event end (inclusive).
            if ($end->gt($eventEnds)) {
                throw ValidationException::withMessages([
                    "signup_periods.{$i}" => [__('ui.events.signup_period_end_before_event_end')],
                ]);
            }

            if ($end->lt($nowUtc)) {
                throw ValidationException::withMessages([
                    "signup_periods.{$i}" => [__('ui.events.signup_period_end_not_past')],
                ]);
            }

            $maxRaw = $row['max_activities'] ?? null;
            $max = null;
            if ($maxRaw !== null && $maxRaw !== '') {
                $max = (int) $maxRaw;
                if ($max < 0) {
                    throw ValidationException::withMessages([
                        "signup_periods.{$i}.max_activities" => [__('ui.events.signup_period_max_invalid')],
                    ]);
                }
                if ($max === 0) {
                    $max = null;
                }
            }

            $normalized[] = [
                'starts_at' => $start,
                'ends_at' => $end,
                'max_activities' => $max,
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
                    'signup_periods' => [__('ui.events.signup_periods_must_not_overlap')],
                ]);
            }
        }

        return $normalized;
    }
}
