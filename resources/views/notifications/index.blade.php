<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-base-content">
            {{ __('ui.notifications.page_title') }}
        </h2>
    </x-slot>

    <livewire:notifications.notification-list />
</x-app-layout>
