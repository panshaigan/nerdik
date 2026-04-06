<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Event;
use Illuminate\Support\Facades\Auth;

class InterestController extends Controller
{
    public function addEvent(Event $event)
    {
        Auth::user()->interestedEvents()->syncWithoutDetaching([$event->id]);

        return redirect()->back()->with('status', __('ui.interests.added_event'));
    }

    public function removeEvent(Event $event)
    {
        Auth::user()->interestedEvents()->detach($event->id);

        return redirect()->back()->with('status', __('ui.interests.removed_event'));
    }

    public function addActivity(Activity $activity)
    {
        Auth::user()->interestedActivities()->syncWithoutDetaching([$activity->id]);

        return redirect()->back()->with('status', __('ui.interests.added_activity'));
    }

    public function removeActivity(Activity $activity)
    {
        Auth::user()->interestedActivities()->detach($activity->id);

        return redirect()->back()->with('status', __('ui.interests.removed_activity'));
    }
}
