<?php

namespace App\Services;

use App\Enums\UserRequestResolutionOutcome;
use App\Models\Activity;
use App\Models\ActivityUser;
use App\Models\ActivityWaitlistEntry;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class ActivityParticipationService
{
    public function __construct(
        private readonly EventActivitySignupService $signupService,
        private readonly ActivityParticipantRosterService $roster,
    ) {}

    public function join(Activity $activity, User $user, ?array $familiarity = null): RedirectResponse
    {
        if ($response = $this->participationRateLimitedResponse($activity, $user)) {
            return $response;
        }

        if ($msg = $this->signupStateBlockMessage($activity)) {
            return redirect()->back()->with('status', $msg);
        }

        if ($activity->participants()->where('user_id', $user->id)->exists()) {
            return redirect()->back()->with('status', __('You are already participating.'));
        }

        if ($activity->waitlist()->where('user_id', $user->id)->exists()) {
            return redirect()->back()->with('status', __('You are on the waitlist. Leave it first if you want to join directly.'));
        }

        if ($activity->isHostApprovalMode() || $activity->isLotteryMode()) {
            return redirect()->back()->with('status', __('ui.activities.join_requires_waitlist'));
        }

        $count = $activity->participants()->count();
        $max = $activity->max_participants;

        if ($max !== null && $count >= $max) {
            return redirect()->back()->with('status', __('Activity is full. You can join the waitlist.'));
        }

        try {
            $this->signupService->assertCanSignup($activity, $user);
        } catch (ValidationException $e) {
            return redirect()->back()->with('status', $this->firstValidationMessage($e));
        }

        $this->signupService->userJoinActivity($activity, $user, $familiarity);
        $this->recordParticipationMutation($activity, $user);

        return redirect()->back()->with('status', __('You joined the activity.'));
    }

    public function leave(Activity $activity, User $user): RedirectResponse
    {
        if ($response = $this->participationRateLimitedResponse($activity, $user)) {
            return $response;
        }

        if ($msg = $this->signupStateBlockMessage($activity)) {
            return redirect()->back()->with('status', $msg);
        }

        $participant = $activity->participants()->where('user_id', $user->id)->first();
        if (! $participant) {
            return redirect()->back()->with('status', __('You are not participating.'));
        }

        $this->signupService->userLeaveActivity($activity, $participant);
        $this->recordParticipationMutation($activity, $user);

        return redirect()->back()->with('status', __('You left the activity.'));
    }

    public function joinWaitlist(Activity $activity, User $user, ?array $familiarity = null): RedirectResponse
    {
        if ($response = $this->participationRateLimitedResponse($activity, $user)) {
            return $response;
        }

        if ($msg = $this->signupStateBlockMessage($activity)) {
            return redirect()->back()->with('status', $msg);
        }

        if ($activity->participants()->where('user_id', $user->id)->exists()) {
            return redirect()->back()->with('status', __('You are already participating.'));
        }

        if ($activity->waitlist()->where('user_id', $user->id)->exists()) {
            return redirect()->back()->with('status', __('You are already on the waitlist.'));
        }

        $isFull = $activity->max_participants !== null
            && $activity->participants()->count() >= $activity->max_participants;
        if (! $activity->isHostApprovalMode() && ! $activity->isLotteryMode() && ! $isFull) {
            return redirect()->back()->with('status', __('ui.activities.waitlist_only_when_approval_or_full'));
        }

        try {
            $this->signupService->assertCanSignup($activity, $user);
        } catch (ValidationException $e) {
            return redirect()->back()->with('status', $this->firstValidationMessage($e));
        }

        $this->signupService->userJoinWaitlist($activity, $user, $familiarity);
        $this->recordParticipationMutation($activity, $user);

        return redirect()->back()->with('status', __('You joined the waitlist.'));
    }

    public function leaveWaitlist(Activity $activity, User $user): RedirectResponse
    {
        if ($response = $this->participationRateLimitedResponse($activity, $user)) {
            return $response;
        }

        if ($msg = $this->signupStateBlockMessage($activity)) {
            return redirect()->back()->with('status', $msg);
        }

        $entry = $activity->waitlist()->where('user_id', $user->id)->first();
        if (! $entry) {
            return redirect()->back()->with('status', __('You are not on the waitlist.'));
        }

        $this->signupService->userLeaveWaitlist($activity, $entry);
        $this->recordParticipationMutation($activity, $user);

        return redirect()->back()->with('status', __('You left the waitlist.'));
    }

    public function approveWaitlistEntry(Activity $activity, ActivityWaitlistEntry $entry, User $user): RedirectResponse
    {
        if ($msg = $this->signupStateBlockMessage($activity)) {
            return redirect()->back()->with('status', $msg);
        }

        abort_unless($user->canModifyEntity($activity), 403, __('ui.activities.only_host_can_approve_waitlist'));

        if (! $activity->isHostApprovalMode()) {
            return redirect()->back()->with('status', __('ui.activities.approval_not_required_for_activity'));
        }

        abort_unless((int) $entry->activity_id === (int) $activity->id, 404);

        $targetUser = $entry->user;
        if ($targetUser === null) {
            return redirect()->back()->with('status', __('ui.activities.waitlist_entry_invalid'));
        }

        if ($activity->participants()->where('user_id', $targetUser->id)->exists()) {
            return redirect()->back()->with('status', __('ui.activities.user_already_participant'));
        }

        $count = $activity->participants()->count();
        $max = $activity->max_participants;
        if ($max !== null && $count >= $max) {
            return redirect()->back()->with('status', __('Activity is full.'));
        }

        try {
            $this->signupService->assertCanSignup($activity, $targetUser, hostApprovingParticipant: true);
        } catch (ValidationException $e) {
            return redirect()->back()->with('status', $this->firstValidationMessage($e));
        }

        $this->signupService->hostApproveWaitlistEntry($activity, $entry);

        return redirect()->back()->with('status', __('ui.activities.waitlist_entry_approved'));
    }

    public function markParticipantAbsent(ActivityUser $participant, User $user): RedirectResponse
    {
        $activity = $participant->activity;

        abort_unless(
            $user->canManageActivityParticipation($activity),
            403,
            __('ui.activities.only_host_or_organizer_can_mark_absent'),
        );

        $this->roster->markParticipantAbsent($participant);

        return redirect()->back()->with('status', __('Participant marked absent.'));
    }

    public function unmarkAbsent(ActivityUser $participant, User $user): RedirectResponse
    {
        $activity = $participant->activity;

        abort_unless(
            $user->canManageActivityParticipation($activity),
            403,
            __('ui.activities.only_host_or_organizer_can_unmark_absent'),
        );

        if (! $participant->is_absent) {
            return redirect()->back()->with('status', __('ui.activities.participant_not_absent'));
        }

        $this->roster->clearParticipantAbsent($participant);

        return redirect()->back()->with('status', __('ui.activities.participant_unmarked_absent'));
    }

    public function moveParticipantToWaitlist(ActivityUser $participant, User $user): RedirectResponse
    {
        $activity = $participant->activity;

        if ($msg = $this->signupStateBlockMessage($activity)) {
            return redirect()->back()->with('status', $msg);
        }

        abort_unless($user->canModifyEntity($activity), 403, __('ui.activities.only_host_can_move_to_waitlist'));

        if ((int) $participant->user_id === (int) ($activity->created_by ?? 0)) {
            return redirect()->back()->with('status', __('ui.activities.cannot_move_host_to_waitlist'));
        }

        if ($activity->waitlist()->where('user_id', $participant->user_id)->exists()) {
            return redirect()->back()->with('status', __('ui.activities.user_already_on_waitlist'));
        }

        $this->roster->moveParticipantToWaitlist($participant);

        return redirect()->back()->with('status', __('ui.activities.participant_moved_to_waitlist'));
    }

    public function removeParticipant(ActivityUser $participant, User $user): RedirectResponse
    {
        $activity = $participant->activity;

        if ($msg = $this->signupStateBlockMessage($activity)) {
            return redirect()->back()->with('status', $msg);
        }

        abort_unless($user->canModifyEntity($activity), 403, __('ui.activities.only_host_can_remove_participant'));

        if ((int) $participant->user_id === (int) ($activity->created_by ?? 0)) {
            return redirect()->back()->with('status', __('ui.activities.cannot_remove_host_from_participants'));
        }

        $this->roster->removeParticipant($participant);

        return redirect()->back()->with('status', __('ui.activities.participant_removed'));
    }

    /**
     * Announce or clear lateness for a participant or host (upserts the host roster row when needed).
     */
    public function setParticipantLateMinutes(Activity $activity, User $actor, int $targetUserId, ?int $lateMinutes): RedirectResponse
    {
        abort_unless(
            $activity->isLateAnnounceWindowOpen(),
            403,
            __('ui.activities.late_announce_window_closed'),
        );

        if ($lateMinutes !== null && ($lateMinutes < 1 || $lateMinutes > 240)) {
            throw ValidationException::withMessages([
                'late_minutes' => [__('ui.activities.late_minutes_invalid')],
            ]);
        }

        $isSelf = (int) $actor->id === $targetUserId;
        $isHostTarget = (int) ($activity->created_by ?? 0) === $targetUserId;
        $targetIsParticipant = $activity->participants()->where('user_id', $targetUserId)->exists();

        if ($isSelf) {
            $actorIsHost = (int) ($activity->created_by ?? 0) === (int) $actor->id;
            abort_unless(
                $targetIsParticipant || $actorIsHost,
                403,
                __('ui.activities.late_announce_forbidden'),
            );
        } else {
            abort_unless(
                $actor->canManageActivityParticipation($activity),
                403,
                __('ui.activities.only_host_or_organizer_can_set_late'),
            );
            abort_unless(
                $targetIsParticipant || $isHostTarget,
                403,
                __('ui.activities.late_announce_target_invalid'),
            );
        }

        $this->roster->setLateMinutes($activity, $targetUserId, $lateMinutes);

        if ($lateMinutes === null) {
            return redirect()->back()->with('status', __('ui.activities.late_cleared'));
        }

        return redirect()->back()->with('status', __('ui.activities.late_announced', ['minutes' => $lateMinutes]));
    }

    /**
     * Apply the same join / waitlist rules as the public participation UI when accepting an activity invite.
     *
     * @throws ValidationException when participation is not currently allowed
     */
    public function participateAsInvitedUser(Activity $activity, User $user, ?array $familiarity = null): UserRequestResolutionOutcome
    {
        $block = $this->signupStateBlockMessage($activity);
        if ($block !== null) {
            throw ValidationException::withMessages(['_' => [(string) $block]]);
        }

        if ($activity->participants()->where('user_id', $user->id)->exists()) {
            return UserRequestResolutionOutcome::Joined;
        }

        if ($activity->waitlist()->where('user_id', $user->id)->exists()) {
            return UserRequestResolutionOutcome::Waitlisted;
        }

        $familiarity ??= app(ActivityFamiliarityService::class)->consumeStagedSnapshot((int) $user->id);

        $isFull = $activity->max_participants !== null
            && $activity->participants()->count() >= $activity->max_participants;

        if (! $activity->isHostApprovalMode() && ! $activity->isLotteryMode() && ! $isFull) {
            $this->signupService->assertCanSignup($activity, $user);
            $this->signupService->userJoinActivity($activity, $user, $familiarity);

            return UserRequestResolutionOutcome::Joined;
        }

        if ($activity->isHostApprovalMode() || $activity->isLotteryMode() || $isFull) {
            $this->signupService->assertCanSignup($activity, $user);
            $this->signupService->userJoinWaitlist($activity, $user, $familiarity);

            return UserRequestResolutionOutcome::Waitlisted;
        }

        throw ValidationException::withMessages([
            '_' => [__('ui.activities.waitlist_only_when_approval_or_full')],
        ]);
    }

    protected function signupStateBlockMessage(Activity $activity): array|string|null
    {
        if ($activity->isCancelled()) {
            return __('ui.activities.signup_blocked_cancelled');
        }
        if (! $activity->isJoinableMode()) {
            return __('ui.activities.signup_blocked_not_joinable_mode');
        }

        return null;
    }

    protected function firstValidationMessage(ValidationException $e): string
    {
        $messages = $e->errors();

        return (string) (collect($messages)->flatten()->first() ?? __('Validation failed.'));
    }

    protected function participationRateLimitedResponse(Activity $activity, User $user): ?RedirectResponse
    {
        $key = $this->participationRateLimitKey($activity, $user);
        $max = (int) config('notification_throttle.participation_mutations_per_minute', 3);

        if (RateLimiter::tooManyAttempts($key, $max)) {
            return redirect()->back()->with('status', __('ui.activities.participation_rate_limited'));
        }

        return null;
    }

    protected function recordParticipationMutation(Activity $activity, User $user): void
    {
        RateLimiter::hit($this->participationRateLimitKey($activity, $user), 60);
    }

    protected function participationRateLimitKey(Activity $activity, User $user): string
    {
        return "participation:{$user->id}:{$activity->id}";
    }
}
