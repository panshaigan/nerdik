<x-app-layout>
    <div class="relative isolate">
        <livewire:events.show-event :event="$event" wire:key="event-show-{{ $event->id }}" />
    </div>
</x-app-layout>
