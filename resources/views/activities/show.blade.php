<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-base-content">
            {{ $activity->name }}
        </h2>
    </x-slot>

    <livewire:activities.show-activity :activity="$activity" wire:key="activity-show-{{ $activity->id }}" />
</x-app-layout>
