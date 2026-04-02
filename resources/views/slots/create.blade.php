<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-base-content">
            {{ request('mode') === 'mass' ? __('ui.slots.mass_create_slots') : __('ui.slots.create_slot') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="rounded-lg border border-base-300 bg-base-100 p-6 shadow">
                @include('slots.mass-create', [
                    'lockedEvent' => $lockedEvent ?? null,
                    'events' => $events,
                    'tags' => $tags,
                    'slotNameSuggestions' => $slotNameSuggestions,
                    'slotBaseNameSuggestions' => $slotBaseNameSuggestions,
                    'slotMassVenues' => $slotMassVenues,
                    'slotMassRoomsByVenueId' => $slotMassRoomsByVenueId,
                    'embeddedInModal' => false,
                    'editMode' => false,
                    'countDefault' => request('mode') === 'mass' ? 5 : 1,
                ])
            </div>
        </div>
    </div>
</x-app-layout>
