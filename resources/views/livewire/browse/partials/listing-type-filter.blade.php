{{-- Mutually exclusive: only one of only_events / only_activities, or neither for both. --}}
<div data-ui="browse-listing-type-filter">
    <div class="flex w-full flex-col gap-4 p-3 md:flex-row md:items-center">
        <div class="flex items-center">
            <x-group
                wire:model.live="tags_match_all"
                data-ui="browse-tag-filter-match-mode"
                legend-class="mb-0"
                class="ui-browse-filter-toggle rounded-2xl btn-sm"
                :options="[
                    ['id' => 0, 'name' => __('ui.browse.tags_match_any')],
                    ['id' => 1, 'name' => __('ui.browse.tags_match_all')],
                ]"
            >
            </x-group>
        </div>
        <span class="flex min-w-0 flex-1 flex-col flex-wrap gap-4 md:flex-row md:items-center">
            <x-checkbox
                wire:model.live="include_past_events"
                :label="__('ui.browse.include_past_events')"
                data-ui="browse-include-past-events"
            />
            <x-checkbox
                wire:model.live="only_events"
                :label="__('ui.browse.only_events')"
                data-ui="browse-only-events"
            />
            <x-checkbox
                wire:model.live="only_activities"
                :label="__('ui.browse.only_activities')"
                data-ui="browse-only-activities"
            />
            <x-checkbox
                wire:model.live="only_free_places"
                :label="__('ui.browse.only_free_places')"
                data-ui="browse-only-free-places"
            />
            @auth
                <x-checkbox
                    wire:model.live="only_mine"
                    :label="__('ui.browse.only_mine')"
                    data-ui="browse-only-mine"
                />
            @endauth
            <span
                class="browse-events-save-clear-toolbar"
                data-ui="browse-events-save-clear-toolbar"
            >
                <x-button
                    type="button"
                    wire:click="saveSearchParams"
                    wire:key="browse-events-save-search"
                    class="btn btn-ghost btn-sm"
                    :title="__('ui.browse.save_search')"
                    :aria-label="__('ui.browse.save_search')"
                    data-ui="browse-events-save-search"
                >
                    <x-icon name="o-bookmark" class="h-4 w-4 shrink-0" />
                    {{ __('ui.browse.save_search') }}
                </x-button>
                <x-button
                    type="button"
                    wire:click="clearFilters"
                    wire:key="browse-events-clear"
                    wire:loading.attr="disabled"
                    class="btn btn-ghost btn-sm"
                    :title="__('ui.browse.clear')"
                    :aria-label="__('ui.browse.clear')"
                    data-ui="browse-events-clear"
                >
                    <x-icon name="o-x-mark" class="h-4 w-4 shrink-0" />
                    {{ __('ui.browse.clear') }}
                </x-button>
            </span>
        </span>
    </div>
</div>
