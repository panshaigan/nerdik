@php
    $prefix = $sortIdPrefix ?? 'browse';
@endphp
<div class="flex flex-wrap items-center gap-2 sm:gap-3" data-ui="browse-sort-controls">
    <span class="text-sm text-base-content/80">{{ __('ui.browse.sort_by') }}</span>
    <label class="sr-only" for="{{ $prefix }}-sort-field">{{ __('ui.browse.sort_by') }}</label>
    <select
        id="{{ $prefix }}-sort-field"
        wire:model.live="sort"
        class="select select-bordered select-sm min-w-[10rem]"
        data-ui="browse-sort-field"
    >
        <option value="date">{{ __('ui.browse.sort_date') }}</option>
        <option value="name">{{ __('ui.browse.sort_name') }}</option>
    </select>
    <label class="sr-only" for="{{ $prefix }}-sort-dir">{{ __('ui.browse.sort_direction') }}</label>
    <select
        id="{{ $prefix }}-sort-dir"
        wire:model.live="sort_dir"
        class="select select-bordered select-sm min-w-[9rem]"
        data-ui="browse-sort-dir"
    >
        <option value="asc">{{ __('ui.browse.sort_ascending') }}</option>
        <option value="desc">{{ __('ui.browse.sort_descending') }}</option>
    </select>
</div>
