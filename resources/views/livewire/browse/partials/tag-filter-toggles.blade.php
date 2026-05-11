{{-- Tag match mode selector; search and listing filters are rendered in sibling partials. --}}
<div class="ml-auto" data-ui="browse-tag-filter-toggles">
    <div class="flex items-center gap-4">
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
        <button
            type="button"
            class="btn btn-sm btn-accent rounded-2xl"
            x-on:click="filtersOpen = !filtersOpen"
            x-bind:class="{ 'btn-accent': filtersOpen, 'btn-outline btn-neutral hover:btn-accent': !filtersOpen }"
            x-bind:aria-pressed="filtersOpen ? 'true' : 'false'"
        >
            <x-icon name="o-funnel" />
            {{ __('Filters') }}
        </button>
    </div>
</div>
