<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Services\SlotFormService;
use App\Traits\AuthorizesOwnership;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class EventController extends Controller
{
    use AuthorizesOwnership;

    public function __construct(
        private readonly SlotFormService $slotFormService
    ) {}

    /**
     * Mass-create slots from the event page (JSON / no full-page redirect).
     */
    public function massStoreSlots(Request $request, Event $event)
    {
        $this->authorizeCreatedBy($event);

        $request->merge([
            'event_id' => $event->id,
            'mass' => '1',
            'redirect_to_event_slug' => $event->slug,
        ]);

        try {
            $this->slotFormService->performMassCreate($request);
        } catch (ValidationException $e) {
            return response()->json(['message' => __('The given data was invalid.'), 'errors' => $e->errors()], 422);
        }

        return response()->json(['ok' => true, 'message' => __('Slots created.')]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('events.create');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Event $event)
    {
        $this->authorizeCreatedBy($event);

        return view('events.edit', compact('event'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Event $event)
    {
        $this->authorizeCreatedBy($event);

        $event->delete();

        return redirect()->route('search.index')
            ->with('status', __('Event deleted.'));
    }

    /**
     * Create a new event by copying an existing one:
     * - copies basic fields
     * - copies slots, but clears activity_id (empty slots)
     */
    public function copy(Event $event)
    {
        if (! auth()->check()) {
            abort(403, __('Unauthorized.'));
        }

        $this->authorizeCreatedBy($event);

        $event->loadMissing(['slots']);

        $newEvent = $event->replicate();
        $newEvent->name = $event->name.' (copy)';
        $newEvent->slug = null; // force auto-generation from name
        $newEvent->created_by = null; // let HasMetaColumns fill current user
        $newEvent->updated_by = null;
        $newEvent->save();

        foreach ($event->slots()->with('place')->get() as $slot) {
            $newSlot = $newEvent->slots()->create([
                'name' => $slot->name,
                'starts_at' => $slot->starts_at,
                'ends_at' => $slot->ends_at,
                'requires_approval' => $slot->requires_approval,
                'max_capacity' => $slot->max_capacity,
                'activity_id' => null, // important: new event has empty slots
            ]);
            if ($slot->place) {
                $newSlot->places()->sync([$slot->place->id]);
            }
        }

        return redirect()->route('events.show', $newEvent)
            ->with('status', __('Event copied.'));
    }
}
