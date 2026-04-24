<x-app-layout>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="rounded-lg border border-base-300 bg-base-100 shadow">
                <livewire:events.manage-event-form :event="$event" wire:key="event-form-{{ $event->id }}" />
            </div>
        </div>
    </div>
</x-app-layout>
