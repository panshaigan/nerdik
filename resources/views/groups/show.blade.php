<x-app-layout>
    <x-slot name="header">
        <a href="{{ route('groups.index') }}" wire:navigate class="text-sm text-base-content/70 hover:text-primary">
            {{ __('ui.groups.title') }}
        </a>
    </x-slot>

    <livewire:groups.show-group :group-slug="$group->slug" />
</x-app-layout>
