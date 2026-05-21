<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Services\CancellationNotificationDispatcher;
use App\Services\EventEmptySlotCloneService;
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
        abort_unless(auth()->user()?->canCreateEvents(), 403, __('ui.events.only_event_organizers_can_create'));

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

        abort_if(
            $event->organiserHardDeleteBlockedWhileActive(),
            403,
            __('ui.events.delete_forbidden_use_cancel')
        );

        $cancelledBy = auth()->user();
        if ($cancelledBy !== null && ! $event->isCancelled()) {
            app(CancellationNotificationDispatcher::class)->notifyEventCancelled($event, $cancelledBy);
        }

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
        abort_unless(auth()->user()?->canCreateEvents(), 403, __('ui.events.only_event_organizers_can_create'));

        $this->authorizeCreatedBy($event);

        $event->loadMissing(['slots']);

        $newEvent = $event->replicate(['search_vector']);
        $newEvent->name = $event->name.' (copy)';
        $newEvent->slug = null; // force auto-generation from name
        $newEvent->created_by = null; // let HasMetaColumns fill current user
        $newEvent->updated_by = null;
        $newEvent->save();

        app(EventEmptySlotCloneService::class)->cloneEmptySlots($event, $newEvent);

        return redirect()->route('events.show', $newEvent)
            ->with('status', __('Event copied.'));
    }
}
