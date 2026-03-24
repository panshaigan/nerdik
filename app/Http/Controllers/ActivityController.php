<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Tag;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
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
        $tags = Tag::with('translations')->orderBy('category')->orderBy('slug')->get();

        return view('activities.create', [
            'activity' => new Activity,
            'tags' => $tags,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $this->validateData($request);
        $request->validate([
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', 'exists:tags,id'],
        ]);

        $tagIds = $request->input('tag_ids', []);

        $activity = Activity::create($validated);
        $activity->tags()->sync($tagIds);

        return redirect()->route('activities.index')
            ->with('status', __('Activity created.'));
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
        $tags = Tag::with('translations')->orderBy('category')->orderBy('slug')->get();
        $activity->load('tags');

        return view('activities.edit', compact('activity', 'tags'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Activity $activity)
    {
        $validated = $this->validateData($request, $activity);
        $request->validate([
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', 'exists:tags,id'],
        ]);

        $tagIds = $request->input('tag_ids', []);

        $activity->update($validated);
        $activity->tags()->sync($tagIds);

        return redirect()->route('activities.index')
            ->with('status', __('Activity updated.'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Activity $activity)
    {
        $activity->delete();

        return redirect()->route('activities.index')
            ->with('status', __('Activity deleted.'));
    }

    protected function validateData(Request $request, ?Activity $activity = null): array
    {
        $id = $activity?->id;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'max:50'],
            'host_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'min_participants' => ['nullable', 'integer', 'min:1'],
            'max_participants' => ['nullable', 'integer', 'min:1'],
            'age_limit' => ['nullable', 'integer', 'min:0'],
            'duration_minutes' => ['nullable', 'integer', 'min:0'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'signoff_deadline_hours' => ['nullable', 'integer', 'min:0'],
            'slug' => ['required', 'string', 'max:255', 'unique:activities,slug,'.$id],
            'is_restricted' => ['nullable', 'boolean'],
            'open_for_observers' => ['nullable', 'boolean'],
        ]);

        $validated['is_restricted'] = $request->boolean('is_restricted');
        $validated['open_for_observers'] = $request->boolean('open_for_observers');

        return $validated;
    }
}
