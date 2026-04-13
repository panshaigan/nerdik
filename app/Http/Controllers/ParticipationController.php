<?php

namespace App\Http\Controllers;

use App\Jobs\NotifyWaitlistPromotedJob;
use App\Models\Activity;
use App\Models\ActivityUser;
use App\Models\ActivityWaitlistEntry;
use App\Services\ActivityParticipantRosterService;
use App\Services\EventActivitySignupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ParticipationController extends Controller
{
    public function join(Activity $activity, EventActivitySignupService $signupService)
    {
        $user = Auth::user();

        if ($msg = $this->signupStateBlockMessage($activity)) {
            return redirect()->back()->with('status', $msg);
        }

        if ($activity->participants()->where('user_id', $user->id)->exists()) {
            return redirect()->back()->with('status', __('You are already participating.'));
        }

        if ($activity->waitlist()->where('user_id', $user->id)->exists()) {
            return redirect()->back()->with('status', __('You are on the waitlist. Leave it first if you want to join directly.'));
        }

        if ($activity->requires_approval) {
            return redirect()->back()->with('status', __('ui.activities.join_requires_waitlist'));
        }

        $count = $activity->participants()->count();
        $max = $activity->max_participants;

        if ($max !== null && $count >= $max) {
            return redirect()->back()->with('status', __('Activity is full. You can join the waitlist.'));
        }

        try {
            $signupService->assertCanSignup($activity, $user);
        } catch (ValidationException $e) {
            return redirect()->back()->with('status', $this->firstValidationMessage($e));
        }

        $signupService->userJoinActivity($activity, $user);

        return redirect()->back()->with('status', __('You joined the activity.'));
    }

    protected function firstValidationMessage(ValidationException $e): string
    {
        $messages = $e->errors();

        return (string) (collect($messages)->flatten()->first() ?? __('Validation failed.'));
    }

    public function leave(Activity $activity, EventActivitySignupService $signupService)
    {
        $user = Auth::user();

        if ($msg = $this->signupStateBlockMessage($activity)) {
            return redirect()->back()->with('status', $msg);
        }

        $participant = $activity->participants()->where('user_id', $user->id)->first();
        if (! $participant) {
            return redirect()->back()->with('status', __('You are not participating.'));
        }

        $signupService->userLeaveActivity($activity, $participant);

        return redirect()->back()->with('status', __('You left the activity.'));
    }

    public function joinWaitlist(Activity $activity, EventActivitySignupService $signupService)
    {
        $user = Auth::user();

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
        if (! $activity->requires_approval && ! $isFull) {
            return redirect()->back()->with('status', __('ui.activities.waitlist_only_when_approval_or_full'));
        }

        try {
            $signupService->assertCanSignup($activity, $user);
        } catch (ValidationException $e) {
            return redirect()->back()->with('status', $this->firstValidationMessage($e));
        }

        $signupService->userJoinWaitlist($activity, $user);

        return redirect()->back()->with('status', __('You joined the waitlist.'));
    }

    public function leaveWaitlist(Activity $activity, EventActivitySignupService $signupService)
    {
        $user = Auth::user();

        if ($msg = $this->signupStateBlockMessage($activity)) {
            return redirect()->back()->with('status', $msg);
        }

        $entry = $activity->waitlist()->where('user_id', $user->id)->first();
        if (! $entry) {
            return redirect()->back()->with('status', __('You are not on the waitlist.'));
        }

        $signupService->userLeaveWaitlist($activity, $entry);

        return redirect()->back()->with('status', __('You left the waitlist.'));
    }

    public function approveWaitlistEntry(Activity $activity, ActivityWaitlistEntry $entry, EventActivitySignupService $signupService)
    {
        $user = Auth::user();

        if ($msg = $this->signupStateBlockMessage($activity)) {
            return redirect()->back()->with('status', $msg);
        }

        abort_unless($user->canModifyEntity($activity), 403, __('ui.activities.only_host_can_approve_waitlist'));

        if (! $activity->requires_approval) {
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
            $signupService->assertCanSignup($activity, $targetUser, hostApprovingParticipant: true);
        } catch (ValidationException $e) {
            return redirect()->back()->with('status', $this->firstValidationMessage($e));
        }

        $signupService->hostApproveWaitlistEntry($activity, $entry);

        return redirect()->back()->with('status', __('ui.activities.waitlist_entry_approved'));
    }

    public function markAbsent(Request $request, ActivityUser $participant, ActivityParticipantRosterService $roster)
    {
        $activity = $participant->activity;
        $user = Auth::user();

        abort_unless($user->canModifyEntity($activity), 403, __('Only the activity host can mark participants absent.'));

        $roster->markParticipantAbsent($participant);

        return redirect()->back()->with('status', __('Participant marked absent.'));
    }

    public function unmarkAbsent(ActivityUser $participant, ActivityParticipantRosterService $roster)
    {
        $activity = $participant->activity;
        $user = Auth::user();

        abort_unless($user->canModifyEntity($activity), 403, __('ui.activities.only_host_can_unmark_absent'));

        if (! $participant->is_absent) {
            return redirect()->back()->with('status', __('ui.activities.participant_not_absent'));
        }

        $roster->clearParticipantAbsent($participant);

        return redirect()->back()->with('status', __('ui.activities.participant_unmarked_absent'));
    }

    public function moveParticipantToWaitlist(ActivityUser $participant, ActivityParticipantRosterService $roster)
    {
        $activity = $participant->activity;
        $user = Auth::user();

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

        $roster->moveParticipantToWaitlist($participant);

        return redirect()->back()->with('status', __('ui.activities.participant_moved_to_waitlist'));
    }

    protected function signupStateBlockMessage(Activity $activity): ?string
    {
        if ($activity->isCancelled()) {
            return __('ui.activities.signup_blocked_cancelled');
        }
        if (! $activity->isJoinableMode()) {
            return __('ui.activities.signup_blocked_not_joinable_mode');
        }

        return null;
    }
}
