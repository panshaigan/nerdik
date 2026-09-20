<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-base-content">
            {{ __('ui.organizations.title') }}
        </h2>
    </x-slot>

    <livewire:organizations.organization-index />
</x-app-layout>
