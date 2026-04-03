<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-base-content">
            {{ __('Slots') }}
        </h2>
    </x-slot>

    <livewire:slots.slot-index />
</x-app-layout>
