<?php

namespace App\Services;

use App\Models\ActivityUser;
use Illuminate\Support\Facades\DB;

class ActivityParticipantRosterService
{
    /**
     * Remove a participant and append them to the activity waitlist (host-initiated; does not auto-promote others).
     */
    public function moveParticipantToWaitlist(ActivityUser $participant): void
    {
        $activity = $participant->activity;
        $userId = $participant->user_id;

        DB::transaction(function () use ($activity, $participant, $userId) {
            $participant->delete();
            $nextPosition = ((int) $activity->waitlist()->max('position')) + 1;
            $activity->waitlist()->create([
                'user_id' => $userId,
                'position' => $nextPosition,
            ]);
        });
    }

    public function clearParticipantAbsent(ActivityUser $participant): void
    {
        $participant->update(['is_absent' => false]);
    }
}
