<x-app-layout>
    <livewire:activities.show-activity :activity="$activity" wire:key="activity-show-{{ $activity->id }}" />
</x-app-layout>
