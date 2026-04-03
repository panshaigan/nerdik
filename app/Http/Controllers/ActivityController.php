<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Traits\AuthorizesOwnership;

class ActivityController extends Controller
{
    use AuthorizesOwnership;

    /** @var list<string> */
    public const ACTIVITY_TYPES = ['rpg', 'board', 'card', 'larp', 'lecture', 'workshop', 'competition', 'show'];

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $activities = Activity::with('host')
            ->orderBy('created_at', 'desc')
            ->get();

        return view('activities.index', compact('activities'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('activities.create');
    }

    /**
     * Display the specified resource.
     */
    public function show(Activity $activity)
    {
        $activity->load(['host', 'creator', 'tags.translations', 'participants.user', 'waitlist.user']);
        $isParticipant = auth()->check() && $activity->participants()->where('user_id', auth()->id())->exists();
        $onWaitlist = auth()->check() && $activity->waitlist()->where('user_id', auth()->id())->exists();
        $canJoin = auth()->check() && ! $isParticipant && ! $onWaitlist;
        $isFull = $activity->max_participants !== null && $activity->participants()->count() >= $activity->max_participants;
        $isHost = auth()->check() && $activity->host_user_id === auth()->id();
        $inWishlist = auth()->check() && auth()->user()->wishlistActivities()->where('activities.id', $activity->id)->exists();

        return view('activities.show', compact('activity', 'isParticipant', 'onWaitlist', 'canJoin', 'isFull', 'isHost', 'inWishlist'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Activity $activity)
    {
        $this->authorizeCreatedBy($activity);

        $activity->load('tags');

        return view('activities.edit', compact('activity'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Activity $activity)
    {
        $this->authorizeCreatedBy($activity);

        $activity->delete();

        return redirect()->route('activities.index')
            ->with('status', __('Activity deleted.'));
    }
}
