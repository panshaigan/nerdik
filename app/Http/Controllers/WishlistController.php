<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Event;
use Illuminate\Support\Facades\Auth;

class WishlistController extends Controller
{
    public function addEvent(Event $event)
    {
        Auth::user()->wishlistEvents()->syncWithoutDetaching([$event->id]);

        return redirect()->back()->with('status', __('Added to wishlist.'));
    }

    public function removeEvent(Event $event)
    {
        Auth::user()->wishlistEvents()->detach($event->id);

        return redirect()->back()->with('status', __('Removed from wishlist.'));
    }

    public function addActivity(Activity $activity)
    {
        Auth::user()->wishlistActivities()->syncWithoutDetaching([$activity->id]);

        return redirect()->back()->with('status', __('Added to wishlist.'));
    }

    public function removeActivity(Activity $activity)
    {
        Auth::user()->wishlistActivities()->detach($activity->id);

        return redirect()->back()->with('status', __('Removed from wishlist.'));
    }
}
