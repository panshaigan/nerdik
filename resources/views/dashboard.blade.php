<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-base-content">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <livewire:dashboard.dashboard />
</x-app-layout>
