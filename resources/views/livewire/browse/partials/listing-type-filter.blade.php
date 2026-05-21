{{-- Mutually exclusive: only one of only_events / only_activities, or neither for both. --}}
<div data-ui="browse-listing-type-filter">
    <div class="flex w-full flex-wrap items-center gap-4 p-3">
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
        <div class="flex min-w-0 flex-1 flex-wrap items-center gap-4">
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
            @auth
                <x-checkbox
                    wire:model.live="only_mine"
                    :label="__('ui.browse.only_mine')"
                    data-ui="browse-only-mine"
                />
            @endauth
        </div>
        <x-button
            type="button"
            label="{{ __('Reset') }}"
            class="btn btn-sm ml-auto shrink-0 rounded-2xl ui-browse-filter-toggle ui-browse-filter-toggle--reset"
            wire:click="clearFilters"
            wire:loading.attr="disabled"
        />
    </div>
</div>
