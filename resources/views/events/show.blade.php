<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-base-content">
            {{ $event->name }}
        </h2>
    </x-slot>

    <div class="event-sky-page relative isolate py-2 sm:py-4">
        <div class="pointer-events-none absolute inset-x-0 top-0 -z-10 h-56 bg-gradient-to-b from-primary/10 via-secondary/5 to-transparent"></div>
        <livewire:events.show-event :event="$event" wire:key="event-show-{{ $event->id }}" />
    </div>
</x-app-layout>
