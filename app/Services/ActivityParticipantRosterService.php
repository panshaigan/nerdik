<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\ActivityUser;
use App\Models\User;
use App\Notifications\ActivityRemovedByHostNotification;
use Illuminate\Support\Facades\DB;

class ActivityParticipantRosterService
{
    public function __construct(
        private readonly EventActivitySignupService $signupService,
    ) {}

    public function removeParticipant(ActivityUser $participant): void
    {
        $user = $participant->user;
        $activity = $participant->activity;

        $participant->delete();

        $this->signupService->promoteWaitlistReplacement($activity);

        if ($user instanceof User && $activity !== null) {
            $user->notify(new ActivityRemovedByHostNotification(
                $activity,
                ActivityRemovedByHostNotification::MODE_REMOVED,
            ));
        }

        if ($activity !== null) {
            ActivityParticipationBroadcaster::rosterChanged((int) $activity->id);
        }
    }

    /**
     * Remove a participant and append them to the activity waitlist (host-initiated; does not auto-promote others).
     */
    public function moveParticipantToWaitlist(ActivityUser $participant): void
    {
        $activity = $participant->activity;
        $userId = $participant->user_id;
        $user = $participant->user;

        DB::transaction(function () use ($activity, $participant, $userId) {
            $familiarity = is_array($participant->familiarity) ? $participant->familiarity : null;
            $participant->delete();
            $nextPosition = ((int) $activity->waitlist()->max('position')) + 1;
            $payload = [
                'user_id' => $userId,
                'position' => $nextPosition,
            ];
            if ($familiarity !== null) {
                $payload['familiarity'] = $familiarity;
            }
            $activity->waitlist()->create($payload);
        });

        if ($user instanceof User && $activity !== null) {
            $user->notify(new ActivityRemovedByHostNotification(
                $activity,
                ActivityRemovedByHostNotification::MODE_MOVED_TO_WAITLIST,
            ));
        }

        if ($activity !== null) {
            ActivityParticipationBroadcaster::rosterChanged((int) $activity->id);
        }
    }

    public function clearParticipantAbsent(ActivityUser $participant): void
    {
        $participant->update(['is_absent' => false]);

        ActivityParticipationBroadcaster::rosterChanged((int) $participant->activity_id);
    }

    public function markParticipantAbsent(ActivityUser $participant): void
    {
        $participant->update(['is_absent' => true]);

        ActivityParticipationBroadcaster::rosterChanged((int) $participant->activity_id);
    }

    /**
     * Persist lateness for a roster participant, or for the host without enrolling them.
     */
    public function setLateMinutes(Activity $activity, int $userId, ?int $lateMinutes): void
    {
        $isHost = (int) ($activity->created_by ?? 0) === $userId;
        $participant = ActivityUser::query()
            ->where('activity_id', $activity->id)
            ->where('user_id', $userId)
            ->first();

        if ($participant !== null) {
            $participant->update(['late_minutes' => $lateMinutes]);

            if ($isHost && $activity->host_late_minutes !== null) {
                $activity->update(['host_late_minutes' => null]);
            }

            ActivityParticipationBroadcaster::rosterChanged((int) $activity->id);

            return;
        }

        if ($isHost) {
            $activity->update(['host_late_minutes' => $lateMinutes]);
            ActivityParticipationBroadcaster::rosterChanged((int) $activity->id);

            return;
        }

        // Non-host targets must already be enrolled (enforced by the participation service).
        abort(403, __('ui.activities.late_announce_target_invalid'));
    }
}
