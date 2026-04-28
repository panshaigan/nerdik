{{-- Tag match mode selector; search and listing filters are rendered in sibling partials. --}}
<div class="ml-auto" data-ui="browse-tag-filter-toggles">
    <div class="flex items-center">
        <div class="flex items-center">
            <x-group
                wire:model.live="tags_match_all"
                data-ui="browse-tag-filter-match-mode"
                legend-class="mb-0"
                class="btn"
                :options="[
                    ['id' => 0, 'name' => __('ui.browse.tags_match_any')],
                    ['id' => 1, 'name' => __('ui.browse.tags_match_all')],
                ]"
            >
            </x-group>
        </div>
        <x-button
            icon="o-funnel"
            label="Filters"
            class="ml-2"
        />
    </div>
</div>
