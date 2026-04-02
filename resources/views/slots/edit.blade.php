<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-base-content">
            {{ __('Edit slot') }}: {{ $slot->name }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="rounded-lg border border-base-300 bg-base-100 p-6 shadow">
                @include('slots.mass-create', [
                    'editMode' => true,
                    'slot' => $slot,
                    'embeddedInModal' => false,
                    'lockedEvent' => $lockedEvent,
                    'events' => $events,
                    'tags' => $tags,
                    'slotNameSuggestions' => $slotNameSuggestions,
                    'slotBaseNameSuggestions' => $slotBaseNameSuggestions,
                    'slotMassVenues' => $slotMassVenues,
                    'slotMassRoomsByVenueId' => $slotMassRoomsByVenueId,
                    'slotVenueRoomDefaults' => $slotVenueRoomDefaults,
                ])
            </div>
        </div>
    </div>
</x-app-layout>
