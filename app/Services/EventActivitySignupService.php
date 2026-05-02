<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\ActivityUser;
use App\Models\ActivityWaitlistEntry;
use App\Models\Event;
use App\Models\EventEnrollmentWindow;
use App\Models\User;
use App\Notifications\ActivityParticipantJoinedNotification;
use App\Notifications\ActivityParticipantLeftNotification;
use App\Notifications\WaitlistPromotedNotification;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EventActivitySignupService
{
    public function userJoinActivity(Activity $activity, User $user): void
    {
        $activity->participants()->create([
            'user_id' => $user->id,
        ]);

        $fresh = $activity->fresh();
        if ($fresh === null) {
            return;
        }

        $host = $fresh->created_by !== null ? User::find($fresh->created_by) : null;
        if ($host instanceof User && (int) $host->id !== (int) $user->id) {
            $host->notify(new ActivityParticipantJoinedNotification(
                $fresh,
                $user,
                (int) $fresh->participants()->count(),
            ));
        }
    }

    public function userLeaveActivity(Activity $activity, ActivityUser $participant): void
    {
        $promotedUser = null;
        $leavingUser = $participant->user;

        DB::transaction(function () use ($participant, $activity, &$promotedUser): void {
            $participant->delete();

            $first = $activity->waitlist()->orderBy('position')->with('user')->first();
            if ($first) {
                $promotedUser = $first->user;
                $first->delete();
                $activity->participants()->create([
                    'user_id' => $promotedUser->id,
                ]);
                $activity->waitlist()->orderBy('position')->get()->each(function ($entry, $index): void {
                    $entry->update(['position' => $index + 1]);
                });
            }
        });

        $fresh = $activity->fresh();

        if ($promotedUser instanceof User && $fresh !== null) {
            $promotedUser->notify(new WaitlistPromotedNotification($fresh));
        }

        if ($fresh !== null && $leavingUser instanceof User) {
            $host = $fresh->created_by !== null ? User::find($fresh->created_by) : null;
            if ($host instanceof User && (int) $host->id !== (int) $leavingUser->id) {
                $host->notify(new ActivityParticipantLeftNotification(
                    $fresh,
                    $leavingUser,
                    (int) $fresh->participants()->count(),
                    $promotedUser,
                ));
            }
        }
    }

    public function userJoinWaitlist(Activity $activity, User $user): void
    {
        $nextPosition = $activity->waitlist()->max('position') + 1;
        $activity->waitlist()->create([
            'user_id' => $user->id,
            'position' => $nextPosition,
        ]);
    }

    public function userLeaveWaitlist(Activity $activity, ActivityWaitlistEntry $waitlistEntry): void
    {
        $pos = $waitlistEntry->position;
        $waitlistEntry->delete();
        $activity->waitlist()->where('position', '>', $pos)->decrement('position');
    }

    public function hostApproveWaitlistEntry(Activity $activity, ActivityWaitlistEntry $waitlistEntry): void
    {
        $targetUser = $waitlistEntry->user;

        DB::transaction(function () use ($activity, $waitlistEntry): void {
            $pos = $waitlistEntry->position;
            $waitlistEntry->delete();
            $activity->waitlist()->where('position', '>', $pos)->decrement('position');
            $activity->participants()->create([
                'user_id' => $waitlistEntry->user_id,
            ]);
        });

        $targetUser->notify(new WaitlistPromotedNotification($activity->fresh()));
    }

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

    /**
     * How many participants joined this activity during the period.
     */
    public function activitySignupCountDuringPeriod(Activity $activity, EventEnrollmentWindow $period): int
    {
        return (int) ActivityUser::query()
            ->where('activity_id', $activity->id)
            ->whereBetween('created_at', [$period->starts_at, $period->ends_at])
            ->count();
    }

    /**
     * Additional quota available from prior accumulative windows.
     */
    public function carryOverSignupAllowanceForPeriod(Event $event, User $user, EventEnrollmentWindow $active): int
    {
        $allowance = 0;

        foreach ($event->enrollmentWindows->sortBy('starts_at') as $window) {
            if (! $window->accumulative_activities) {
                continue;
            }
            if ($window->ends_at === null || $active->starts_at === null || ! $window->ends_at->lt($active->starts_at)) {
                continue;
            }

            $max = $window->maxActivitiesPerUserEffective();
            if ($max === null) {
                continue;
            }

            $used = $this->userSignupCountDuringPeriod($event, $user, $window);
            $allowance += max(0, $max - $used);
        }

        return $allowance;
    }

    public function effectiveUserSignupLimitForPeriod(Event $event, User $user, EventEnrollmentWindow $active): ?int
    {
        $current = $active->maxActivitiesPerUserEffective();
        if ($current === null) {
            return null;
        }
        if (! $active->accumulative_activities) {
            return $current;
        }

        return $current + $this->carryOverSignupAllowanceForPeriod($event, $user, $active);
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

        if ($activity->isCancelled()) {
            throw ValidationException::withMessages([
                '_' => [__('ui.activities.signup_blocked_cancelled')],
            ]);
        }

        if (! $activity->isJoinableMode()) {
            throw ValidationException::withMessages([
                '_' => [__('ui.activities.signup_blocked_not_joinable_mode')],
            ]);
        }

        $slot = $activity->slot;
        if ($slot === null || $slot->event_id === null) {
            return;
        }

        $event = $slot->event;
        if ($event === null) {
            return;
        }

        if ($event->isCancelled()) {
            throw ValidationException::withMessages([
                '_' => [__('ui.events.signup_blocked_event_cancelled')],
            ]);
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

        $activityMax = $active->maxAllowedParticipantsPerActivityEffective();
        if ($activityMax !== null) {
            $takenInWindow = $this->activitySignupCountDuringPeriod($activity, $active);
            if ($takenInWindow >= $activityMax) {
                throw ValidationException::withMessages([
                    '_' => [__('ui.events.enrollment_window_activity_capacity_reached', ['max' => $activityMax])],
                ]);
            }
        }

        $max = $this->effectiveUserSignupLimitForPeriod($event, $user, $active);
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
     * @param  list<array{
     *   name?: string,
     *   starts_at: string,
     *   ends_at: string,
     *   max_activities_per_user?: mixed,
     *   max_allowed_participants_per_activity?: mixed,
     *   accumulative_activities?: mixed
     * }>  $rows
     * @return list<array{
     *   name: string|null,
     *   starts_at: Carbon,
     *   ends_at: Carbon,
     *   max_activities_per_user: int|null,
     *   max_allowed_participants_per_activity: int|null,
     *   accumulative_activities: bool
     * }>
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
            $name = isset($row['name']) ? trim((string) $row['name']) : '';
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

            $perActivityRaw = $row['max_allowed_participants_per_activity'] ?? null;
            $perActivity = null;
            if ($perActivityRaw !== null && $perActivityRaw !== '') {
                $perActivity = (int) $perActivityRaw;
                if ($perActivity < 0) {
                    throw ValidationException::withMessages([
                        "enrollment_windows.{$i}.max_allowed_participants_per_activity" => [__('ui.events.enrollment_window_activity_max_invalid')],
                    ]);
                }
                if ($perActivity === 0) {
                    $perActivity = null;
                }
            }

            $accumulative = (bool) ($row['accumulative_activities'] ?? false);

            $normalized[] = [
                'name' => $name !== '' ? $name : null,
                'starts_at' => $start,
                'ends_at' => $end,
                'max_activities_per_user' => $max,
                'max_allowed_participants_per_activity' => $perActivity,
                'accumulative_activities' => $accumulative,
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
