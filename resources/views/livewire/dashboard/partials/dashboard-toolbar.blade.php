@php
    /** @var bool $feedIsEmpty */
    /** @var bool $groupsPanelOpen */
@endphp

<section class="space-y-4" data-ui="dashboard-toolbar">
    @if ($feedIsEmpty)
        <p class="text-sm text-base-content/70">{{ __('ui.dashboard.empty_hint') }}</p>
    @endif

    <div class="flex flex-wrap gap-2" data-ui="dashboard-quick-actions">
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
            :link="route('search.index')"
            wire:navigate
            class="btn-ghost btn-sm"
            icon="o-magnifying-glass"
            :label="__('ui.nav.search')"
            data-ui="dashboard-action-search"
        />

        <x-button
            type="button"
            wire:click="toggleGroupsPanel"
            wire:loading.attr="disabled"
            wire:target="toggleGroupsPanel"
            class="btn-ghost btn-sm {{ $groupsPanelOpen ? 'btn-active' : '' }}"
            icon="o-user-group"
            :label="__('ui.nav.my_groups')"
            :aria-expanded="$groupsPanelOpen ? 'true' : 'false'"
            aria-controls="dashboard-groups-panel-region"
            spinner="toggleGroupsPanel"
            data-ui="dashboard-action-groups"
        />
    </div>

    @if ($groupsPanelOpen)
        <div id="dashboard-groups-panel-region">
            @include('livewire.dashboard.partials.groups-panel', $dashboardGroupsStrip)
        </div>
    @endif
</section>
