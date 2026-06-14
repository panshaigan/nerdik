<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Event;
use App\Services\UserInterestService;
use Illuminate\Support\Facades\Auth;

class InterestController extends Controller
{
    public function addEvent(Event $event, UserInterestService $interests)
    {
        $interests->addEventInterest(Auth::user(), $event);

        return redirect()->back()->with('status', __('ui.interests.added_event'));
    }

    public function removeEvent(Event $event, UserInterestService $interests)
    {
        $interests->removeEventInterest(Auth::user(), $event);

        return redirect()->back()->with('status', __('ui.interests.removed_event'));
    }

    public function addActivity(Activity $activity, UserInterestService $interests)
    {
        $interests->addActivityInterest(Auth::user(), $activity);

        return redirect()->back()->with('status', __('ui.interests.added_activity'));
    }

    public function removeActivity(Activity $activity, UserInterestService $interests)
    {
        $interests->removeActivityInterest(Auth::user(), $activity);

        return redirect()->back()->with('status', __('ui.interests.removed_activity'));
    }
}
