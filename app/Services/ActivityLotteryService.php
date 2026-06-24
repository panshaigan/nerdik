<?php

namespace App\Services;

use App\Enums\ParticipationMode;
use App\Models\Activity;
use App\Models\ActivityWaitlistEntry;
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
     * When the initial lottery draw should run (earliest applicable trigger).
     */
    public function resolveAt(Activity $activity): ?Carbon
    {
        $candidates = array_filter([
            $this->enrollmentWindowEndAt($activity),
            $this->cancellationDeadlineAt($activity),
        ]);

        if ($candidates === []) {
            return null;
        }

        return collect($candidates)->sort()->first();
    }

    public function hasResolvableTrigger(Activity $activity): bool
    {
        return $this->resolveAt($activity) !== null;
    }

    /**
     * @return Collection<int, Activity>
     */
    public function dueActivities(): Collection
    {
        $now = Carbon::now();

        return Activity::query()
            ->where('participation_mode', ParticipationMode::Lottery->value)
            ->whereNull('lottery_resolved_at')
            ->whereNull('cancelled_at')
            ->with(['slot.event.enrollmentWindows', 'waitlist.user', 'participants'])
            ->get()
            ->filter(function (Activity $activity) use ($now): bool {
                $resolveAt = $this->resolveAt($activity);

                return $resolveAt !== null && $resolveAt->lte($now);
            })
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
        if (! $activity->isLotteryMode() || $activity->isLotteryResolved()) {
            return;
        }

        DB::transaction(function () use ($activity): void {
            $fresh = Activity::query()
                ->whereKey($activity->id)
                ->lockForUpdate()
                ->with(['waitlist.user', 'participants'])
                ->first();

            if ($fresh === null || $fresh->isLotteryResolved()) {
                return;
            }

            $this->fillOpenSpotsFromWaitlist($fresh, notify: true);

            $fresh->update(['lottery_resolved_at' => now()]);
        });

        ActivityParticipationBroadcaster::rosterChanged((int) $activity->id);
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

    protected function fillOpenSpotsFromWaitlist(Activity $activity, bool $notify): void
    {
        while (! $this->isAtCapacity($activity)) {
            $promoted = $this->pickAndPromoteRandomEntry($activity, $notify);

            if ($promoted === null) {
                break;
            }
        }
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

    protected function enrollmentWindowEndAt(Activity $activity): ?Carbon
    {
        $activity->loadMissing('slot.event.enrollmentWindows');
        $event = $activity->slot?->event;
        if ($event === null) {
            return null;
        }

        $windows = $event->enrollmentWindows;
        if ($windows->isEmpty()) {
            return null;
        }

        $latestEnd = $windows->max('ends_at');

        return $latestEnd instanceof Carbon ? $latestEnd->copy() : null;
    }

    protected function cancellationDeadlineAt(Activity $activity): ?Carbon
    {
        $activity->loadMissing('slot');
        $activityStart = $activity->slot?->starts_at ?? $activity->starts_at;
        if ($activityStart === null || $activity->cancellation_deadline_in_hours === null) {
            return null;
        }

        return $activityStart->copy()->subHours((int) $activity->cancellation_deadline_in_hours);
    }
}
