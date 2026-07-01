<?php

namespace App\Services;

use App\Enums\LotteryDrawTrigger;
use App\Enums\ParticipationMode;
use App\Models\Activity;
use App\Models\ActivityLotteryDraw;
use App\Models\ActivityWaitlistEntry;
use App\Models\EventEnrollmentWindow;
use App\Models\User;
use App\Notifications\WaitlistPromotedNotification;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ActivityLotteryService
{
    public function __construct(
        private readonly EventActivitySignupService $signupService,
    ) {}

    /**
     * @return Collection<int, LotteryDrawSchedule>
     */
    public function scheduledDraws(Activity $activity): Collection
    {
        if (! $activity->isLotteryMode()) {
            return collect();
        }

        $finalAt = $this->lotteryDrawAt($activity);
        $windowDraws = $this->enrollmentWindowDrawSchedules($activity);

        if ($windowDraws->isEmpty()) {
            if ($finalAt === null) {
                return collect();
            }

            return collect([
                new LotteryDrawSchedule($finalAt, LotteryDrawTrigger::Final),
            ]);
        }

        $schedules = collect();

        foreach ($windowDraws as $windowSchedule) {
            if ($finalAt !== null && $windowSchedule->at->equalTo($finalAt)) {
                continue;
            }

            $schedules->push($windowSchedule);
        }

        if ($finalAt !== null) {
            $schedules->push(new LotteryDrawSchedule($finalAt, LotteryDrawTrigger::Final));
        }

        return $schedules->sortBy(fn (LotteryDrawSchedule $schedule): int => $schedule->at->timestamp)->values();
    }

    public function hasResolvableTrigger(Activity $activity): bool
    {
        return $this->scheduledDraws($activity)->isNotEmpty();
    }

    /**
     * @deprecated Use scheduledDraws() for multi-draw scheduling.
     */
    public function resolveAt(Activity $activity): ?Carbon
    {
        return $this->scheduledDraws($activity)->first()?->at;
    }

    /**
     * @return Collection<int, LotteryDrawSchedule>
     */
    public function dueDraws(Activity $activity, ?Carbon $now = null): Collection
    {
        $now ??= Carbon::now();
        $activity->loadMissing('lotteryDraws');

        return $this->scheduledDraws($activity)
            ->filter(function (LotteryDrawSchedule $schedule) use ($activity, $now): bool {
                if ($schedule->at->gt($now)) {
                    return false;
                }

                return ! $this->hasCompletedDraw($activity, $schedule);
            })
            ->values();
    }

    /**
     * @return Collection<int, Activity>
     */
    public function dueActivities(): Collection
    {
        return Activity::query()
            ->where('participation_mode', ParticipationMode::Lottery->value)
            ->whereNull('cancelled_at')
            ->with(['slot.event.enrollmentWindows', 'waitlist.user', 'participants', 'lotteryDraws'])
            ->get()
            ->filter(fn (Activity $activity): bool => $this->dueDraws($activity)->isNotEmpty())
            ->values();
    }

    public function resolveDueActivities(): int
    {
        $count = 0;

        foreach ($this->dueActivities() as $activity) {
            $this->resolveActivity($activity);
            $count++;
        }

        return $count;
    }

    public function resolveActivity(Activity $activity): void
    {
        if (! $activity->isLotteryMode()) {
            return;
        }

        $ranAny = false;

        DB::transaction(function () use ($activity, &$ranAny): void {
            $fresh = Activity::query()
                ->whereKey($activity->id)
                ->lockForUpdate()
                ->with(['slot.event.enrollmentWindows', 'waitlist.user', 'participants', 'lotteryDraws'])
                ->first();

            if ($fresh === null) {
                return;
            }

            foreach ($this->dueDraws($fresh) as $schedule) {
                if ($this->hasCompletedDraw($fresh, $schedule)) {
                    continue;
                }

                $this->runDraw($fresh, $schedule);
                $fresh->load('lotteryDraws');
                $ranAny = true;
            }
        });

        if ($ranAny) {
            ActivityParticipationBroadcaster::rosterChanged((int) $activity->id);
        }
    }

    public function runDraw(Activity $activity, LotteryDrawSchedule $schedule): void
    {
        if ($schedule->trigger === LotteryDrawTrigger::Final) {
            $this->fillOpenSpotsFromWaitlist($activity, notify: true);
            $activity->update(['lottery_resolved_at' => now()]);
        } else {
            $spots = $this->spotsForWindowDraw($activity, $schedule->enrollmentWindow);
            $this->fillSpotsFromWaitlist($activity, $spots, notify: true);
        }

        ActivityLotteryDraw::query()->create([
            'activity_id' => $activity->id,
            'trigger' => $schedule->trigger,
            'enrollment_window_id' => $schedule->enrollmentWindowId(),
            'drawn_at' => now(),
        ]);
    }

    public function promoteRandomEligibleEntry(Activity $activity): ?User
    {
        if (! $activity->isLotteryMode() || ! $activity->isLotteryResolved()) {
            return null;
        }

        $promotedUser = null;

        DB::transaction(function () use ($activity, &$promotedUser): void {
            $fresh = Activity::query()
                ->whereKey($activity->id)
                ->lockForUpdate()
                ->with(['waitlist.user'])
                ->first();

            if ($fresh === null || ! $fresh->isLotteryResolved()) {
                return;
            }

            if ($this->isAtCapacity($fresh)) {
                return;
            }

            $promotedUser = $this->pickAndPromoteRandomEntry($fresh, notify: true);
        });

        if ($promotedUser instanceof User) {
            ActivityParticipationBroadcaster::rosterChanged((int) $activity->id);
        }

        return $promotedUser;
    }

    public function lotteryDrawAt(Activity $activity): ?Carbon
    {
        return $activity->lotteryDrawAt();
    }

    /**
     * @return Collection<int, LotteryDrawSchedule>
     */
    public function upcomingDraws(Activity $activity, ?Carbon $now = null): Collection
    {
        $now ??= Carbon::now();
        $activity->loadMissing('lotteryDraws');

        return $this->scheduledDraws($activity)
            ->filter(fn (LotteryDrawSchedule $schedule): bool => $schedule->at->gt($now) && ! $this->hasCompletedDraw($activity, $schedule))
            ->values();
    }

    /**
     * @return list<array{message: string, dataUi: string}>
     */
    public function upcomingDrawNotices(Activity $activity, string $dataUiPrefix = 'activity-show'): array
    {
        if (! $activity->isLotteryMode()) {
            return [];
        }

        $notices = [];

        foreach ($this->upcomingDraws($activity) as $schedule) {
            $when = format_datetime_in_user_tz($schedule->at);

            if ($schedule->trigger === LotteryDrawTrigger::Final) {
                $notices[] = [
                    'message' => __('ui.activities.lottery_draw_final_notice', ['when' => $when]),
                    'dataUi' => "{$dataUiPrefix}-lottery-draw-final",
                ];

                continue;
            }

            $window = $schedule->enrollmentWindow;
            $max = $window?->maxAllowedParticipantsPerActivityEffective();

            $notices[] = [
                'message' => $max !== null
                    ? __('ui.activities.lottery_draw_window_notice', [
                        'max' => $max,
                        'window' => $window?->name ?? '',
                        'when' => $when,
                    ])
                    : __('ui.activities.lottery_draw_window_unlimited_notice', [
                        'window' => $window?->name ?? '',
                        'when' => $when,
                    ]),
                'dataUi' => "{$dataUiPrefix}-lottery-draw-window-".($window?->id ?? 'unknown'),
            ];
        }

        return $notices;
    }

    protected function fillOpenSpotsFromWaitlist(Activity $activity, bool $notify): void
    {
        while (! $this->isAtCapacity($activity)) {
            $promoted = $this->pickAndPromoteRandomEntry($activity, $notify);

            if ($promoted === null) {
                break;
            }
        }
    }

    protected function fillSpotsFromWaitlist(Activity $activity, int $spots, bool $notify): void
    {
        if ($spots <= 0) {
            return;
        }

        $filled = 0;

        while ($filled < $spots && ! $this->isAtCapacity($activity)) {
            $promoted = $this->pickAndPromoteRandomEntry($activity, $notify);

            if ($promoted === null) {
                break;
            }

            $filled++;
        }
    }

    protected function spotsForWindowDraw(Activity $activity, ?EventEnrollmentWindow $window): int
    {
        $openSpots = $this->openSpots($activity);

        if ($openSpots <= 0) {
            return 0;
        }

        if ($window === null) {
            return $openSpots;
        }

        $windowCap = $window->maxAllowedParticipantsPerActivityEffective();

        if ($windowCap === null) {
            return $openSpots;
        }

        $takenInWindow = $this->signupService->activitySignupCountDuringPeriod($activity, $window);
        $remainingInWindow = max(0, $windowCap - $takenInWindow);

        return min($openSpots, $remainingInWindow);
    }

    protected function openSpots(Activity $activity): int
    {
        if ($activity->max_participants === null) {
            return PHP_INT_MAX;
        }

        return max(0, $activity->max_participants - $activity->participants()->count());
    }

    protected function pickAndPromoteRandomEntry(Activity $activity, bool $notify): ?User
    {
        $entries = $activity->waitlist()->with('user')->get()->shuffle();

        foreach ($entries as $entry) {
            $user = $entry->user;
            if (! $user instanceof User) {
                continue;
            }

            if ($activity->participants()->where('user_id', $user->id)->exists()) {
                $this->removeWaitlistEntry($activity, $entry);

                continue;
            }

            try {
                $this->signupService->assertCanSignup($activity, $user, forLotteryResolution: true);
            } catch (ValidationException) {
                continue;
            }

            $this->promoteWaitlistEntry($activity, $entry, $notify);

            return $user;
        }

        return null;
    }

    protected function promoteWaitlistEntry(Activity $activity, ActivityWaitlistEntry $entry, bool $notify): void
    {
        $targetUser = $entry->user;

        $pos = $entry->position;
        $entry->delete();
        $activity->waitlist()->where('position', '>', $pos)->decrement('position');
        $activity->participants()->create([
            'user_id' => $entry->user_id,
        ]);

        if ($notify && $targetUser instanceof User) {
            $targetUser->notify(new WaitlistPromotedNotification($activity->fresh()));
        }
    }

    protected function removeWaitlistEntry(Activity $activity, ActivityWaitlistEntry $entry): void
    {
        $pos = $entry->position;
        $entry->delete();
        $activity->waitlist()->where('position', '>', $pos)->decrement('position');
    }

    protected function isAtCapacity(Activity $activity): bool
    {
        if ($activity->max_participants === null) {
            return false;
        }

        return $activity->participants()->count() >= $activity->max_participants;
    }

    /**
     * @return Collection<int, LotteryDrawSchedule>
     */
    protected function enrollmentWindowDrawSchedules(Activity $activity): Collection
    {
        $activity->loadMissing('slot.event.enrollmentWindows');
        $event = $activity->slot?->event;
        if ($event === null) {
            return collect();
        }

        $windows = $event->enrollmentWindows->sortBy('ends_at');

        if ($windows->isEmpty()) {
            return collect();
        }

        return $windows
            ->map(fn (EventEnrollmentWindow $window): LotteryDrawSchedule => new LotteryDrawSchedule(
                $window->ends_at->copy(),
                LotteryDrawTrigger::EnrollmentWindowEnd,
                $window,
            ))
            ->values();
    }

    protected function hasCompletedDraw(Activity $activity, LotteryDrawSchedule $schedule): bool
    {
        return $activity->lotteryDraws->contains(function (ActivityLotteryDraw $draw) use ($schedule): bool {
            if ($draw->trigger !== $schedule->trigger) {
                return false;
            }

            if ($schedule->trigger === LotteryDrawTrigger::Final) {
                return true;
            }

            return (int) $draw->enrollment_window_id === (int) $schedule->enrollmentWindowId();
        });
    }
}
