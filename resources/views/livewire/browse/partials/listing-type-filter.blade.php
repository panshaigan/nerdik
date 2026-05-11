{{-- Mutually exclusive: only one of only_events / only_activities, or neither for both. --}}
<div data-ui="browse-listing-type-filter">
        <div class="flex items-center gap-4 p-3">
            <div class="flex items-center">
                <x-group
                    wire:model.live="tags_match_all"
                    data-ui="browse-tag-filter-match-mode"
                    legend-class="mb-0"
                    class="[&:checked]:!btn-accent rounded-2xl btn-sm"
                    :options="[
                    ['id' => 0, 'name' => __('ui.browse.tags_match_any')],
                    ['id' => 1, 'name' => __('ui.browse.tags_match_all')],
                ]"
                >
                </x-group>
            </div>
            <x-button
                type="button"
                label="{{ __('Reset') }}"
                class="btn-outline btn-sm btn-neutral rounded-2xl"
                wire:click="clearFilters"
                wire:loading.attr="disabled"
            />
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
        </div>
</div>
