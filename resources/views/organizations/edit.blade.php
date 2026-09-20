<x-app-layout>
    <livewire:organizations.manage-organization-form :organization="$organization" wire:key="organization-edit-{{ $organization->id }}" />
</x-app-layout>
