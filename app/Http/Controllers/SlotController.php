<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Slot;
use App\Services\SlotFormService;
use App\Traits\AuthorizesOwnership;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SlotController extends Controller
{
    use AuthorizesOwnership;

    public function __construct(
        private readonly SlotFormService $slotFormService
    ) {}

    /**
     * Slot edit form fragment for the event-page modal (fetched via XHR).
     */
    public function edit(Request $request, Slot $slot): View
    {
        $this->authorizeCreatedBy($slot);

        $slot->load(['event.places', 'place.parent', 'activityTypes']);

        $events = Event::orderBy('starts_at', 'desc')->get();

        $slotNameSuggestions = Slot::distinctNameSuggestionsForUser(auth()->id());
        $slotBaseNameSuggestions = Slot::baseNameSuggestionsForUser(auth()->id());

        $massPlaceData = $this->slotFormService->massFormPlaceDataForAllEvents();

        $slotVenueRoomDefaults = $this->slotFormService->slotVenueRoomDefaultsFromPlace($slot);

        $lockedEvent = $slot->event;
        $slotMassVenues = $this->slotFormService->venuesForEventMassForm($slot->event);
        $slotMassRoomsByVenueId = $this->slotFormService->roomOptionsByVenueId($slotMassVenues);

        $payload = array_merge(
            compact(
                'slot',
                'events',
                'slotNameSuggestions',
                'slotBaseNameSuggestions',
                'slotMassVenues',
                'slotMassRoomsByVenueId',
                'slotVenueRoomDefaults',
                'lockedEvent'
            ),
            $massPlaceData
        );

        return view('slots.edit-modal', $payload);
    }

    /**
     * Update the specified resource in storage.
     *
     * @return JsonResponse|RedirectResponse
     */
    public function update(Request $request, Slot $slot): RedirectResponse|JsonResponse
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
}
