<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Organization;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

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

        return view('events.create', [
            'event' => new Event(),
            'organizations' => $organizations,
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
        ]);

        $validated['created_by'] = Auth::id();
        $validated['is_public'] = $request->boolean('is_public', true);

        Event::create($validated);

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

        return view('events.edit', compact('event', 'organizations'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Event $event)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'organization_id' => ['nullable', 'exists:organizations,id'],
            'slug' => ['required', 'string', 'max:255', 'unique:events,slug,' . $event->id],
            'desc' => ['nullable', 'string'],
            'is_public' => ['nullable', 'boolean'],
        ]);

        $validated['is_public'] = $request->boolean('is_public', true);

        $event->update($validated);

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
