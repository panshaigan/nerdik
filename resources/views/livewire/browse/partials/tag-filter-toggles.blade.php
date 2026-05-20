@props([
    'buttonClass' => 'btn btn-sm btn-accent rounded-2xl',
])

{{-- Tag match mode selector; search and listing filters are rendered in sibling partials. --}}
<div data-ui="browse-tag-filter-toggles">
    <button
        type="button"
        class="{{ $buttonClass }}"
        x-on:click="filtersOpen = !filtersOpen"
        x-bind:class="{ 'is-active': filtersOpen }"
        x-bind:aria-pressed="filtersOpen ? 'true' : 'false'"
    >
        <x-icon name="o-funnel" />
        {{ __('Filters') }}
    </button>
</div>
