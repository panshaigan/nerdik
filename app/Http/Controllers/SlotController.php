<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Slot;
use App\Models\Tag;
use App\Services\SlotFormService;
use App\Traits\AuthorizesOwnership;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SlotController extends Controller
{
    use AuthorizesOwnership;

    public function __construct(
        private readonly SlotFormService $slotFormService
    ) {}

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        $lockedEvent = null;
        if ($request->filled('event')) {
            $lockedEvent = Event::where('slug', $request->string('event'))->firstOrFail();
        }

        $events = Event::orderBy('starts_at', 'desc')->get();

        $slot = new Slot;
        if ($lockedEvent) {
            $slot->event_id = $lockedEvent->id;
        }

        $slotMassVenues = collect();
        $slotMassRoomsByVenueId = [];
        if ($lockedEvent) {
            $slotMassVenues = $this->slotFormService->venuesForEventMassForm($lockedEvent);
            $slotMassRoomsByVenueId = $this->slotFormService->roomOptionsByVenueId($slotMassVenues);
        }

        $tags = Tag::with(['translations', 'aliases', 'attachedTags'])->orderBy('category')->orderBy('slug')->get();
        $slotNameSuggestions = Slot::distinctNameSuggestionsForUser(auth()->id());
        $slotBaseNameSuggestions = Slot::baseNameSuggestionsForUser(auth()->id());
        $massPlaceData = $this->slotFormService->massFormPlaceDataForAllEvents();

        return view('slots.create', [
            'slot' => $slot,
            'events' => $events,
            'lockedEvent' => $lockedEvent,
            'tags' => $tags,
            'slotNameSuggestions' => $slotNameSuggestions,
            'slotBaseNameSuggestions' => $slotBaseNameSuggestions,
            'slotMassVenues' => $slotMassVenues,
            'slotMassRoomsByVenueId' => $slotMassRoomsByVenueId,
        ] + $massPlaceData);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if (! $request->boolean('mass')) {
            return redirect()->route('slots.create');
        }

        return $this->slotFormService->massCreate($request);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Request $request, Slot $slot)
    {
        $this->authorizeCreatedBy($slot);

        $slot->load(['tags', 'event.places', 'place.parent', 'activityTypes']);

        $events = Event::orderBy('starts_at', 'desc')->get();

        $tags = Tag::with(['translations', 'aliases', 'attachedTags'])->orderBy('category')->orderBy('slug')->get();

        $slotNameSuggestions = Slot::distinctNameSuggestionsForUser(auth()->id());
        $slotBaseNameSuggestions = Slot::baseNameSuggestionsForUser(auth()->id());

        $massPlaceData = $this->slotFormService->massFormPlaceDataForAllEvents();

        $slotVenueRoomDefaults = $this->slotFormService->slotVenueRoomDefaultsFromPlace($slot);

        $lockedEvent = $request->boolean('modal') ? $slot->event : null;

        $slotMassVenues = $lockedEvent ? $this->slotFormService->venuesForEventMassForm($slot->event) : collect();
        $slotMassRoomsByVenueId = $lockedEvent ? $this->slotFormService->roomOptionsByVenueId($slotMassVenues) : [];

        $payload = array_merge(
            compact(
                'slot',
                'events',
                'tags',
                'slotNameSuggestions',
                'slotBaseNameSuggestions',
                'slotMassVenues',
                'slotMassRoomsByVenueId',
                'slotVenueRoomDefaults',
                'lockedEvent'
            ),
            $massPlaceData
        );

        if ($request->boolean('modal')) {
            return view('slots.edit-modal', $payload);
        }

        return view('slots.edit', $payload);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Slot $slot)
    {
        $this->authorizeCreatedBy($slot);

        if ($request->expectsJson()) {
            try {
                $this->slotFormService->performSlotUpdate($request, $slot);
            } catch (ValidationException $e) {
                return response()->json(['errors' => $e->errors()], 422);
            }

            return response()->json(['ok' => true]);
        }

        return $this->slotFormService->updateSlot($request, $slot);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Slot $slot)
    {
        $this->authorizeCreatedBy($slot);

        $slot->delete();

        return redirect()->route('slots.index')
            ->with('status', __('Slot deleted.'));
    }
}
