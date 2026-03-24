<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Organization;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EventController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $events = Event::with('organization')
            ->orderBy('created_at', 'desc')
            ->get();

        return view('events.index', compact('events'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $organizations = Organization::orderBy('name')->get();
        $tags = Tag::with('translations')->orderBy('category')->orderBy('slug')->get();

        return view('events.create', [
            'event' => new Event,
            'organizations' => $organizations,
            'tags' => $tags,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'organization_id' => ['nullable', 'exists:organizations,id'],
            'slug' => ['required', 'string', 'max:255', 'unique:events,slug'],
            'desc' => ['nullable', 'string'],
            'is_public' => ['nullable', 'boolean'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', 'exists:tags,id'],
        ]);

        $validated['created_by'] = Auth::id();
        $validated['is_public'] = $request->boolean('is_public', true);

        $tagIds = $validated['tag_ids'] ?? [];
        unset($validated['tag_ids']);

        $event = Event::create($validated);
        $event->tags()->sync($tagIds);

        return redirect()->route('events.index')
            ->with('status', __('Event created.'));
    }

    /**
     * Display the specified resource.
     */
    public function show(Event $event)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Event $event)
    {
        $organizations = Organization::orderBy('name')->get();
        $tags = Tag::with('translations')->orderBy('category')->orderBy('slug')->get();
        $event->load('tags');

        return view('events.edit', compact('event', 'organizations', 'tags'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Event $event)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'organization_id' => ['nullable', 'exists:organizations,id'],
            'slug' => ['required', 'string', 'max:255', 'unique:events,slug,'.$event->id],
            'desc' => ['nullable', 'string'],
            'is_public' => ['nullable', 'boolean'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', 'exists:tags,id'],
        ]);

        $validated['is_public'] = $request->boolean('is_public', true);

        $tagIds = $validated['tag_ids'] ?? [];
        unset($validated['tag_ids']);

        $event->update($validated);
        $event->tags()->sync($tagIds);

        return redirect()->route('events.index')
            ->with('status', __('Event updated.'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Event $event)
    {
        $event->delete();

        return redirect()->route('events.index')
            ->with('status', __('Event deleted.'));
    }
}
