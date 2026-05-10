<x-app-layout>
    <livewire:events.manage-event-form :event="$event" wire:key="event-form-{{ $event->id }}" />
</x-app-layout>
