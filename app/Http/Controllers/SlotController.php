<?php

namespace App\Http\Controllers;

use App\Models\EventInstance;
use App\Models\Place;
use App\Models\Slot;
use Illuminate\Http\Request;

class SlotController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $slots = Slot::with(['eventInstance.event', 'place'])
            ->orderBy('starts_at')
            ->get();

        return view('slots.index', compact('slots'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $instances = EventInstance::with('event')
            ->orderBy('starts_at', 'desc')
            ->get();

        $places = Place::orderBy('name')->get();

        return view('slots.create', [
            'slot' => new Slot,
            'instances' => $instances,
            'places' => $places,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if ($request->boolean('mass')) {
            return $this->storeMass($request);
        }

        $validated = $request->validate([
            'event_instance_id' => ['required', 'exists:event_instances,id'],
            'name' => ['required', 'string', 'max:255'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'place_id' => ['nullable', 'exists:places,id'],
            'requires_approval' => ['nullable', 'boolean'],
            'max_capacity' => ['nullable', 'integer', 'min:1'],
        ]);

        $validated['requires_approval'] = $request->boolean('requires_approval');
        if (! empty($validated['starts_at'])) {
            $validated['starts_at'] = parse_datetime_to_utc($validated['starts_at'])?->toDateTimeString();
        }
        if (! empty($validated['ends_at'])) {
            $validated['ends_at'] = parse_datetime_to_utc($validated['ends_at'])?->toDateTimeString();
        }

        Slot::create($validated);

        return redirect()->route('slots.index')
            ->with('status', __('Slot created.'));
    }

    /**
     * Display the specified resource.
     */
    public function show(Slot $slot)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Slot $slot)
    {
        $instances = EventInstance::with('event')
            ->orderBy('starts_at', 'desc')
            ->get();

        $places = Place::orderBy('name')->get();

        return view('slots.edit', compact('slot', 'instances', 'places'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Slot $slot)
    {
        $validated = $request->validate([
            'event_instance_id' => ['required', 'exists:event_instances,id'],
            'name' => ['required', 'string', 'max:255'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'place_id' => ['nullable', 'exists:places,id'],
            'requires_approval' => ['nullable', 'boolean'],
            'max_capacity' => ['nullable', 'integer', 'min:1'],
        ]);

        $validated['requires_approval'] = $request->boolean('requires_approval');
        if (! empty($validated['starts_at'])) {
            $validated['starts_at'] = parse_datetime_to_utc($validated['starts_at'])?->toDateTimeString();
        }
        if (! empty($validated['ends_at'])) {
            $validated['ends_at'] = parse_datetime_to_utc($validated['ends_at'])?->toDateTimeString();
        }

        $slot->update($validated);

        return redirect()->route('slots.index')
            ->with('status', __('Slot updated.'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Slot $slot)
    {
        $slot->delete();

        return redirect()->route('slots.index')
            ->with('status', __('Slot deleted.'));
    }

    /**
     * Mass create slots for an event instance.
     */
    protected function storeMass(Request $request)
    {
        $validated = $request->validate([
            'event_instance_id' => ['required', 'exists:event_instances,id'],
            'base_name' => ['required', 'string', 'max:255'],
            'count' => ['required', 'integer', 'min:1', 'max:100'],
            'starts_at' => ['nullable', 'date'],
            'place_id' => ['nullable', 'exists:places,id'],
            'requires_approval' => ['nullable', 'boolean'],
            'max_capacity' => ['nullable', 'integer', 'min:1'],
        ]);

        $requiresApproval = $request->boolean('requires_approval');
        $startsAtUtc = ! empty($validated['starts_at'])
            ? parse_datetime_to_utc($validated['starts_at'])?->toDateTimeString()
            : null;

        for ($i = 1; $i <= $validated['count']; $i++) {
            Slot::create([
                'event_instance_id' => $validated['event_instance_id'],
                'name' => sprintf('%s #%02d', $validated['base_name'], $i),
                'starts_at' => $startsAtUtc,
                'ends_at' => null,
                'place_id' => $validated['place_id'] ?? null,
                'requires_approval' => $requiresApproval,
                'max_capacity' => $validated['max_capacity'] ?? null,
            ]);
        }

        return redirect()->route('slots.index')
            ->with('status', __('Slots created.'));
    }
}
