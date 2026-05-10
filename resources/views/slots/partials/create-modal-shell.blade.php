@auth
    <dialog id="event-slots-create-modal" class="modal">
        <div class="modal-box max-w-3xl">
            @include('slots.mass-create', [
                'embeddedInModal' => true,
                'editMode' => false,
                'lockedEvent' => $event,
                'slotMassVenues' => $slotMassVenues ?? collect(),
                'slotMassRoomsByVenueId' => $slotMassRoomsByVenueId ?? [],
                'slotBaseNameSuggestions' => $slotBaseNameSuggestions ?? [],
                'slotNameSuggestions' => $slotNameSuggestions ?? [],
                'massFormAction' => route('events.slots.mass', $event),
            ])
        </div>
        <form method="dialog" class="modal-backdrop">
            <x-button type="submit" class="btn-ghost" :aria-label="__('ui.common.cancel')">{{ __('ui.common.cancel') }}</x-button>
        </form>
    </dialog>
@endauth
