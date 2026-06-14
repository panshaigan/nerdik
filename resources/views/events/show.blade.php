<x-app-layout>
    <livewire:events.show-event :event="$event" wire:key="event-show-{{ $event->id }}" />
</x-app-layout>
