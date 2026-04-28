<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-base-content">
            {{ __('ui.me.page_title_activities') }}
        </h2>
    </x-slot>

    <livewire:me.my-activities />
</x-app-layout>
