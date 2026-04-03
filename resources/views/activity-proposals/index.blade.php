<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-base-content">
            {{ __('ui.proposals.activity_proposals') }}
        </h2>
    </x-slot>

    <livewire:activity-proposals.proposal-index />
</x-app-layout>
