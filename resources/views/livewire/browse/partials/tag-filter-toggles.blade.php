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
        <button
            type="button"
            class="btn ml-2"
            x-on:click="filtersOpen = !filtersOpen"
            x-bind:class="{ 'btn-primary': filtersOpen, 'btn-outline': !filtersOpen }"
            x-bind:aria-pressed="filtersOpen ? 'true' : 'false'"
        >
            <x-icon name="o-funnel" />
            {{ __('Filters') }}
        </button>
        <x-button
            type="button"
            label="{{ __('Reset') }}"
            class="btn-outline ml-2"
            wire:click="clearFilters"
            wire:loading.attr="disabled"
        />
    </div>
</div>
