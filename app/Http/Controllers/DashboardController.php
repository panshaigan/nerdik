<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\ActivityParticipant;
use App\Models\ActivityProposal;
use App\Models\Event;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $user = Auth::user();

        $myEvents = Event::with('organization')
            ->where('created_by', $user->id)
            ->orderBy('updated_at', 'desc')
            ->limit(10)
            ->get();

        $myActivities = Activity::with('host')
            ->where('host_user_id', $user->id)
            ->orderBy('updated_at', 'desc')
            ->limit(10)
            ->get();

        $participations = ActivityParticipant::with('activity')
            ->where('user_id', $user->id)
            ->where('is_host', false)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $myProposals = ActivityProposal::with(['activity', 'eventInstance.event'])
            ->where('created_by', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return view('dashboard', compact('myEvents', 'myActivities', 'participations', 'myProposals'));
    }
}
