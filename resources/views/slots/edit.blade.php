<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-base-content">
            {{ __('Edit slot') }}: {{ $slot->name }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="rounded-lg border border-base-300 bg-base-100 p-6 shadow">
                <form method="POST" action="{{ route('slots.update', $slot) }}" data-slot-edit-form>
                    @csrf
                    @method('PUT')
                    @include('slots.form', [
                        'submitLabel' => __('ui.common.save'),
                        'embeddedInModal' => false,
                        'lockedEvent' => null,
                        'slot' => $slot,
                        'events' => $events,
                        'places' => $places,
                        'tags' => $tags,
                        'slotNameSuggestions' => $slotNameSuggestions,
                    ])
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
