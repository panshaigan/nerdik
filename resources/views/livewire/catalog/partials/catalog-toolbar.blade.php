<div
    class="ui-filter-form ui-browse-events-filter-shell mb-10"
    data-ui="catalog-toolbar"
>
    <div class="ui-browse-events-toolbar-search min-w-0 w-full">
        <x-input
            wire:model.live.debounce.300ms="q"
            :placeholder="$placeholder"
            type="search"
            :omit-error="true"
            class="ui-field w-full"
            data-ui="catalog-search"
        />
    </div>
</div>
