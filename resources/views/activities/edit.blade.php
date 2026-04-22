@php
    $breadcrumbs = [
        [
            'label' => '',
            'icon' => 's-home',
            'link' => '/dashboard',
        ],
        [
            'label' => 'Activities',
            'link' => '/activities'
        ],
        [
            'label' => $activity->name,
            'link' => '/activities/'.$activity->slug,
        ],
        [
            'label' => 'Edit',
        ],
    ];
@endphp

<x-app-layout>
    <div class="py-12">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" class="p-2 text-base-content/70" />
            <div class="rounded-lg border border-base-300 bg-base-100 shadow">
                <livewire:activities.manage-activity-form :activity="$activity" wire:key="activity-edit-{{ $activity->id }}" />
            </div>
        </div>
    </div>
</x-app-layout>
