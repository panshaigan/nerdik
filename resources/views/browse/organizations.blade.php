<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-base-content">
            {{ __('Organizations') }}
        </h2>
    </x-slot>

    <livewire:browse.browse-organizations />
</x-app-layout>
