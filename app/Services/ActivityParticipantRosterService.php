<?php

namespace App\Services;

use App\Models\ActivityUser;
use App\Models\User;
use App\Notifications\ActivityRemovedByHostNotification;
use Illuminate\Support\Facades\DB;

class ActivityParticipantRosterService
{
    public function removeParticipant(ActivityUser $participant): void
    {
        $user = $participant->user;
        $activity = $participant->activity;

        $participant->delete();

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
            $participant->delete();
            $nextPosition = ((int) $activity->waitlist()->max('position')) + 1;
            $activity->waitlist()->create([
                'user_id' => $userId,
                'position' => $nextPosition,
            ]);
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
}
