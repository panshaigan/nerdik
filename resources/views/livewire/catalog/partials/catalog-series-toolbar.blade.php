<div
    class="ui-filter-form ui-browse-events-filter-shell mb-10 space-y-4"
    data-ui="catalog-toolbar"
>
    <div class="ui-browse-events-toolbar-search min-w-0 w-full">
        <x-input
            wire:model.live.debounce.300ms="q"
            :placeholder="__('ui.catalog.search_series_placeholder')"
            type="search"
            :omit-error="true"
            class="ui-field w-full"
            data-ui="catalog-search"
        />
    </div>
    @include('livewire.partials.listing-sort-controls', [
        'sortIdPrefix' => 'catalog-series',
        'fields' => ['popularity', 'activities', 'name'],
        'labelNamespace' => 'ui.catalog',
    ])
</div>
