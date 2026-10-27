<x-app-layout>
    <livewire:events.show-event
        :event="$event"
        wire:key="event-show-{{ $event->id }}-user-{{ auth()->id() ?? 'guest' }}"
    />
</x-app-layout>
