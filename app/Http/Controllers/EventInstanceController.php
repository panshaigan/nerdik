<?php

namespace App\Http\Controllers;

use App\Models\EventInstance;
use App\Models\Event;
use Illuminate\Http\Request;

class EventInstanceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $instances = EventInstance::with('event')
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
            'instance' => new EventInstance(),
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

        EventInstance::create($validated);

        return redirect()->route('event-instances.index')
            ->with('status', __('Event instance created.'));
    }

    /**
     * Display the specified resource.
     */
    public function show(EventInstance $eventInstance)
    {
        //
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
            'slug' => ['required', 'string', 'max:255', 'unique:event_instances,slug,' . $eventInstance->id],
            'desc' => ['nullable', 'string'],
        ]);

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
