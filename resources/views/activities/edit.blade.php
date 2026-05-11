<x-app-layout>
    <livewire:activities.manage-activity-form :activity="$activity" wire:key="activity-edit-{{ $activity->id }}" />
</x-app-layout>
