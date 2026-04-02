<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-base-content">
            {{ request('mode') === 'mass' ? __('ui.slots.mass_create_slots') : __('ui.slots.create_slot') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="rounded-lg border border-base-300 bg-base-100 p-6 shadow">
                @if (request('mode') === 'mass')
                    @include('slots.mass-create', [
                        'lockedEvent' => $lockedEvent ?? null,
                        'events' => $events,
                        'places' => $places,
                        'tags' => $tags,
                        'slotNameSuggestions' => $slotNameSuggestions,
                        'embeddedInModal' => false,
                    ])
                @else
                    <form method="POST" action="{{ route('slots.store') }}">
                        @csrf
                        @if (isset($lockedEvent) && $lockedEvent)
                            <input type="hidden" name="redirect_to_event_slug" value="{{ $lockedEvent->slug }}" />
                        @endif
                        @include('slots.form', [
                            'submitLabel' => __('ui.common.create'),
                            'embeddedInModal' => false,
                            'lockedEvent' => $lockedEvent ?? null,
                            'slot' => $slot,
                            'events' => $events,
                            'places' => $places,
                            'tags' => $tags,
                            'slotNameSuggestions' => $slotNameSuggestions,
                        ])
                    </form>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
