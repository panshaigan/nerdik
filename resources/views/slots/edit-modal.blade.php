@include('slots.mass-create', [
    'editMode' => true,
    'slot' => $slot,
    'embeddedInModal' => true,
    'lockedEvent' => $lockedEvent,
    'events' => $events,
    'slotNameSuggestions' => $slotNameSuggestions ?? [],
    'slotBaseNameSuggestions' => $slotBaseNameSuggestions,
    'slotMassVenues' => $slotMassVenues,
    'slotMassRoomsByVenueId' => $slotMassRoomsByVenueId,
    'slotVenueRoomDefaults' => $slotVenueRoomDefaults,
])
