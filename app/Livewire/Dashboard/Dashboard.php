<?php

namespace App\Livewire\Dashboard;

use App\Models\Activity;
use App\Models\ActivityProposal;
use App\Models\ActivityUser;
use App\Models\Event;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Dashboard extends Component
{
    public function render()
    {
        $user = Auth::user();

        $myEvents = Event::with('organization')
            ->where('created_by', $user->id)
            ->orderBy('updated_at', 'desc')
            ->limit(10)
            ->get();

        $myActivities = Activity::with(['creator', 'activityType'])
            ->where('created_by', $user->id)
            ->orderBy('updated_at', 'desc')
            ->limit(10)
            ->get();

        $participations = ActivityUser::with('activity')
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $myProposals = ActivityProposal::with(['activity', 'event'])
            ->where('created_by', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $interestedEvents = $user->interestedEvents()->with(['organization'])->orderBy('name')->limit(10)->get();
        $interestedActivities = $user->interestedActivities()->with('creator')->orderBy('name')->limit(10)->get();

        return view('livewire.dashboard.dashboard', [
            'myEvents' => $myEvents,
            'myActivities' => $myActivities,
            'participations' => $participations,
            'myProposals' => $myProposals,
            'interestedEvents' => $interestedEvents,
            'interestedActivities' => $interestedActivities,
        ]);
    }
}
