<x-app-layout>
    <livewire:events.show-event-series :event-series="$eventSeries" wire:key="event-series-show-{{ $eventSeries->id }}" />
</x-app-layout>
