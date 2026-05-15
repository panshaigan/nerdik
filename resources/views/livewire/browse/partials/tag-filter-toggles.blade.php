{{-- Tag match mode selector; search and listing filters are rendered in sibling partials. --}}
<div data-ui="browse-tag-filter-toggles">
    <div class="flex items-center gap-4">
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
