@props([
    'buttonClass' => 'btn btn-sm btn-accent rounded-2xl',
])

{{-- Tag match mode selector; search and listing filters are rendered in sibling partials.
     Uses $data.filtersOpen so morph teardown does not throw ReferenceError on bare filtersOpen. --}}
<div data-ui="browse-tag-filter-toggles">
    <button
        type="button"
        class="{{ $buttonClass }}"
        x-on:click="$data.filtersOpen = !$data.filtersOpen"
        x-bind:class="{ 'is-active': !!$data.filtersOpen }"
        x-bind:aria-pressed="$data.filtersOpen ? 'true' : 'false'"
        data-ui="browse-filters-toggle"
    >
        <x-icon name="o-funnel" />
        {{ __('ui.browse.filters') }}
    </button>
</div>
