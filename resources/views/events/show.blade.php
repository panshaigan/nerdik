<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-base-content">
            {{ $event->name }}
        </h2>
    </x-slot>

    <livewire:events.show-event :event="$event" wire:key="event-show-{{ $event->id }}" />
</x-app-layout>
