<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-xl font-semibold leading-tight text-base-content">
                {{ __('ui.notifications.page_title') }}
            </h2>
            <p class="mt-1 text-sm text-base-content/60">{{ __('ui.notifications.page_subtitle') }}</p>
        </div>
    </x-slot>

    <livewire:notifications.notification-list />
</x-app-layout>
