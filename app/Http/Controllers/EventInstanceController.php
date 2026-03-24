<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventInstance;
use Illuminate\Http\Request;

class EventInstanceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $instances = EventInstance::with('event.creator')
            ->orderBy('starts_at', 'desc')
            ->get();

        return view('event-instances.index', compact('instances'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $events = Event::orderBy('name')->get();

        return view('event-instances.create', [
            'instance' => new EventInstance,
            'events' => $events,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'event_id' => ['required', 'exists:events,id'],
            'name' => ['nullable', 'string', 'max:255'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'slug' => ['required', 'string', 'max:255', 'unique:event_instances,slug'],
            'desc' => ['nullable', 'string'],
        ]);

        $validated['starts_at'] = parse_datetime_to_utc($validated['starts_at'])?->toDateTimeString();
        $validated['ends_at'] = parse_datetime_to_utc($validated['ends_at'])?->toDateTimeString();

        EventInstance::create($validated);

        return redirect()->route('event-instances.index')
            ->with('status', __('Event instance created.'));
    }

    /**
     * Display the specified resource.
     */
    public function show(EventInstance $eventInstance)
    {
        $eventInstance->load(['event.creator', 'event.tags.translations', 'slots.place', 'slots.activity']);
        $pendingProposals = $eventInstance->proposals()
            ->with(['activity', 'creator'])
            ->where('status', 'pending')
            ->orderBy('created_at')
            ->get();
        $isOwner = auth()->check() && $eventInstance->event->created_by === auth()->id();

        return view('event-instances.show', [
            'instance' => $eventInstance,
            'pendingProposals' => $pendingProposals,
            'isOwner' => $isOwner,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(EventInstance $eventInstance)
    {
        $events = Event::orderBy('name')->get();

        return view('event-instances.edit', [
            'instance' => $eventInstance,
            'events' => $events,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, EventInstance $eventInstance)
    {
        $validated = $request->validate([
            'event_id' => ['required', 'exists:events,id'],
            'name' => ['nullable', 'string', 'max:255'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'slug' => ['required', 'string', 'max:255', 'unique:event_instances,slug,'.$eventInstance->id],
            'desc' => ['nullable', 'string'],
        ]);

        $validated['starts_at'] = parse_datetime_to_utc($validated['starts_at'])?->toDateTimeString();
        $validated['ends_at'] = parse_datetime_to_utc($validated['ends_at'])?->toDateTimeString();

        $eventInstance->update($validated);

        return redirect()->route('event-instances.index')
            ->with('status', __('Event instance updated.'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(EventInstance $eventInstance)
    {
        $eventInstance->delete();

        return redirect()->route('event-instances.index')
            ->with('status', __('Event instance deleted.'));
    }
}
