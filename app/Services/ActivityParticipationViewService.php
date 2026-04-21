<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class ActivityParticipationViewService
{
    public function __construct(
        protected EventActivitySignupService $signupService,
    ) {}

    /**
     * Derived enrollment/join state for the activity show page (Livewire/API).
     */
    public function forShow(Activity $activity, ?User $user): ActivityParticipationViewData
    {
        $isParticipant = $user !== null && $activity->participants()->where('user_id', $user->id)->exists();
        $onWaitlist = $user !== null && $activity->waitlist()->where('user_id', $user->id)->exists();

        $signupGateOk = true;
        $signupBlockedMessage = null;
        $activeWindowPerActivityMax = null;
        $activeWindowRemainingForActivity = null;
        $activeWindowUserRemaining = null;

        $event = $activity->slot?->event;
        $activeEnrollmentWindow = $event !== null
            ? $this->signupService->firstPeriodContaining($event, Carbon::now())
            : null;

        if ($activeEnrollmentWindow !== null) {
            $perActivityMax = $activeEnrollmentWindow->maxAllowedParticipantsPerActivityEffective();
            if ($perActivityMax !== null) {
                $activeWindowPerActivityMax = $perActivityMax;
                $taken = $this->signupService->activitySignupCountDuringPeriod($activity, $activeEnrollmentWindow);
                $activeWindowRemainingForActivity = max(0, $perActivityMax - $taken);
            }
            if ($user !== null) {
                $effectiveLimit = $this->signupService->effectiveUserSignupLimitForPeriod($event, $user, $activeEnrollmentWindow);
                if ($effectiveLimit !== null) {
                    $used = $this->signupService->userSignupCountDuringPeriod($event, $user, $activeEnrollmentWindow);
                    $activeWindowUserRemaining = max(0, $effectiveLimit - $used);
                }
            }
        }

        $stateBlockedMessage = null;
        if ($activity->isCancelled()) {
            $stateBlockedMessage = __('ui.activities.signup_blocked_cancelled');
        } elseif (! $activity->isJoinableMode()) {
            $stateBlockedMessage = __('ui.activities.signup_blocked_not_joinable_mode');
        }

        if (
            $stateBlockedMessage === null
            && $user !== null
            && ! $isParticipant
            && ! $onWaitlist
            && $activity->slot?->event_id
        ) {
            try {
                $this->signupService->assertCanSignup($activity, $user);
            } catch (ValidationException $e) {
                $signupGateOk = false;
                $signupBlockedMessage = collect($e->errors())->flatten()->first();
            }
        }

        $canJoin = $user !== null && ! $isParticipant && ! $onWaitlist && $signupGateOk && $stateBlockedMessage === null;
        $isFull = $activity->max_participants !== null
            && $activity->participants()->count() >= $activity->max_participants;
        $hasInterest = $user !== null
            && $user->interestedActivities()->where('activities.id', $activity->id)->exists();
        $canManageActivity = $user?->canModifyEntity($activity) ?? false;

        return new ActivityParticipationViewData(
            isParticipant: $isParticipant,
            onWaitlist: $onWaitlist,
            canJoin: $canJoin,
            isFull: $isFull,
            hasInterest: $hasInterest,
            canManageActivity: $canManageActivity,
            signupBlockedMessage: $signupBlockedMessage,
            stateBlockedMessage: $stateBlockedMessage,
            activeWindowPerActivityMax: $activeWindowPerActivityMax,
            activeWindowRemainingForActivity: $activeWindowRemainingForActivity,
            activeWindowUserRemaining: $activeWindowUserRemaining,
        );
    }
}
