<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\ActivityUser;
use App\Models\ActivityWaitlistEntry;
use App\Services\ActivityParticipationService;
use Illuminate\Support\Facades\Auth;

class ParticipationController extends Controller
{
    public function join(Activity $activity, ActivityParticipationService $participation)
    {
        return $participation->join($activity, Auth::user());
    }

    public function leave(Activity $activity, ActivityParticipationService $participation)
    {
        return $participation->leave($activity, Auth::user());
    }

    public function joinWaitlist(Activity $activity, ActivityParticipationService $participation)
    {
        return $participation->joinWaitlist($activity, Auth::user());
    }

    public function leaveWaitlist(Activity $activity, ActivityParticipationService $participation)
    {
        return $participation->leaveWaitlist($activity, Auth::user());
    }

    public function approveWaitlistEntry(Activity $activity, ActivityWaitlistEntry $entry, ActivityParticipationService $participation)
    {
        return $participation->approveWaitlistEntry($activity, $entry, Auth::user());
    }

    public function markAbsent(ActivityUser $participant, ActivityParticipationService $participation)
    {
        return $participation->markParticipantAbsent($participant, Auth::user());
    }

    public function unmarkAbsent(ActivityUser $participant, ActivityParticipationService $participation)
    {
        return $participation->unmarkAbsent($participant, Auth::user());
    }

    public function moveParticipantToWaitlist(ActivityUser $participant, ActivityParticipationService $participation)
    {
        return $participation->moveParticipantToWaitlist($participant, Auth::user());
    }

    public function removeParticipant(ActivityUser $participant, ActivityParticipationService $participation)
    {
        return $participation->removeParticipant($participant, Auth::user());
    }
}
