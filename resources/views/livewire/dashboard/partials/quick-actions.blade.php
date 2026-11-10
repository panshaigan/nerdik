@php
    $compact = $compact ?? false;
@endphp

<section
    class="{{ $compact ? 'space-y-2' : 'space-y-4 rounded-2xl border border-dashed border-base-300 bg-base-100/50 p-6' }}"
    data-ui="dashboard-quick-actions"
>
    @if (! $compact)
        <p class="text-sm text-base-content/70">{{ __('ui.dashboard.empty_hint') }}</p>
    @endif

    <div class="flex flex-wrap gap-2">
        <x-button
            :link="url_with_return(route('activities.create'))"
            wire:navigate
            class="btn-primary btn-sm"
            icon="o-plus"
            :label="__('ui.nav.create_activity')"
            data-ui="dashboard-action-create-activity"
        />

        @if (auth()->user()->canCreateEvents())
            <x-button
                :link="url_with_return(route('events.create'))"
                wire:navigate
                class="btn-secondary btn-sm"
                icon="o-plus-circle"
                :label="__('ui.nav.create_event')"
                data-ui="dashboard-action-create-event"
            />
        @endif

        <x-button
            :link="route('groups.index')"
            wire:navigate
            class="btn-ghost btn-sm"
            icon="o-user-group"
            :label="__('ui.nav.my_groups')"
            data-ui="dashboard-action-groups"
        />

        <x-button
            :link="route('search.index')"
            wire:navigate
            class="btn-ghost btn-sm"
            icon="o-magnifying-glass"
            :label="__('ui.nav.search')"
            data-ui="dashboard-action-search"
        />
    </div>
</section>
