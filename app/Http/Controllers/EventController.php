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
            ->orderBy('starts_at', 'desc')
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
            'desc' => ['nullable', 'string'],
            'is_public' => ['nullable', 'boolean'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', 'exists:tags,id'],
        ]);

        $validated['created_by'] = Auth::id();
        $validated['is_public'] = $request->boolean('is_public', true);
        $validated['starts_at'] = parse_datetime_to_utc($validated['starts_at'])?->toDateTimeString();
        $validated['ends_at'] = parse_datetime_to_utc($validated['ends_at'])?->toDateTimeString();

        $tagIds = $validated['tag_ids'] ?? [];
        unset($validated['tag_ids']);

        // Slug is auto-generated in the model (from `name`).
        unset($validated['slug']);

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
        $event->load(['creator', 'tags.translations', 'organization', 'slots.place', 'slots.activity']);
        $pendingProposals = $event->proposals()
            ->with(['activity', 'creator'])
            ->where('status', 'pending')
            ->orderBy('created_at')
            ->get();
        $isOwner = auth()->check() && $event->created_by === auth()->id();

        return view('events.show', compact('event', 'pendingProposals', 'isOwner'));
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
            'desc' => ['nullable', 'string'],
            'is_public' => ['nullable', 'boolean'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', 'exists:tags,id'],
        ]);

        $validated['is_public'] = $request->boolean('is_public', true);
        $validated['starts_at'] = parse_datetime_to_utc($validated['starts_at'])?->toDateTimeString();
        $validated['ends_at'] = parse_datetime_to_utc($validated['ends_at'])?->toDateTimeString();

        $tagIds = $validated['tag_ids'] ?? [];
        unset($validated['tag_ids']);

        // Slug is auto-generated in the model (from `name`).
        unset($validated['slug']);

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
