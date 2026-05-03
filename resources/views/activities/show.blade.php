<x-app-layout>
    <div class="contents" data-show-activity-id="{{ $activity->id }}">
        <livewire:activities.show-activity :activity="$activity" wire:key="activity-show-{{ $activity->id }}" />
    </div>
</x-app-layout>
