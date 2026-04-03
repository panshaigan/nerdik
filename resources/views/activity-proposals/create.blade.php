<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-base-content">
            {{ __('ui.proposals.propose_activity') }} · {{ $event->name }}
        </h2>
    </x-slot>

    <livewire:activity-proposals.create-proposal-form :event="$event" wire:key="propose-{{ $event->id }}" />
</x-app-layout>
