<?php

namespace App\Http\Controllers;

use App\Jobs\NotifyWaitlistPromotedJob;
use App\Models\Activity;
use App\Models\ActivityUser;
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

        $activity->participants()->create([
            'user_id' => $user->id,
        ]);

        return redirect()->back()->with('status', __('You joined the activity.'));
    }

    protected function firstValidationMessage(ValidationException $e): string
    {
        $messages = $e->errors();

        return (string) (collect($messages)->flatten()->first() ?? __('Validation failed.'));
    }

    public function leave(Activity $activity)
    {
        $user = Auth::user();

        $participant = $activity->participants()->where('user_id', $user->id)->first();
        if (! $participant) {
            return redirect()->back()->with('status', __('You are not participating.'));
        }

        DB::transaction(function () use ($participant, $activity) {
            $participant->delete();

            $first = $activity->waitlist()->orderBy('position')->with('user')->first();
            if ($first) {
                $promotedUser = $first->user;
                $first->delete();
                $activity->participants()->create([
                    'user_id' => $promotedUser->id,
                ]);
                $activity->waitlist()->orderBy('position')->get()->each(function ($entry, $index) {
                    $entry->update(['position' => $index + 1]);
                });
                NotifyWaitlistPromotedJob::dispatch($promotedUser, $activity);
            }
        });

        return redirect()->back()->with('status', __('You left the activity.'));
    }

    public function joinWaitlist(Activity $activity, EventActivitySignupService $signupService)
    {
        $user = Auth::user();

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

        $nextPosition = $activity->waitlist()->max('position') + 1;
        $activity->waitlist()->create([
            'user_id' => $user->id,
            'position' => $nextPosition,
        ]);

        return redirect()->back()->with('status', __('You joined the waitlist.'));
    }

    public function leaveWaitlist(Activity $activity)
    {
        $user = Auth::user();

        $entry = $activity->waitlist()->where('user_id', $user->id)->first();
        if (! $entry) {
            return redirect()->back()->with('status', __('You are not on the waitlist.'));
        }

        $pos = $entry->position;
        $entry->delete();
        $activity->waitlist()->where('position', '>', $pos)->decrement('position');

        return redirect()->back()->with('status', __('You left the waitlist.'));
    }

    public function markAbsent(Request $request, ActivityUser $participant)
    {
        $activity = $participant->activity;
        $user = Auth::user();

        if ((int) $activity->created_by !== (int) $user->id) {
            abort(403, __('Only the activity host can mark participants absent.'));
        }

        $participant->update(['is_absent' => true]);

        return redirect()->back()->with('status', __('Participant marked absent.'));
    }
}
