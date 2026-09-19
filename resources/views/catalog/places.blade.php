<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-base-content">
            {{ __('ui.catalog.places_title') }}
        </h2>
    </x-slot>

    <livewire:catalog.catalog-places />
</x-app-layout>
