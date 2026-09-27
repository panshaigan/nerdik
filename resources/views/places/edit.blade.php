<x-app-layout>
    <livewire:places.manage-place-form :place="$place" wire:key="place-edit-{{ $place->id }}" />
</x-app-layout>
