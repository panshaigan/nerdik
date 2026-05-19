@php
    $prefix = $sortIdPrefix ?? 'browse';
    $dirAsc = strtolower($sort_dir ?? 'asc') === 'asc';
@endphp
<div class="flex flex-wrap items-center gap-x-6 gap-y-2" data-ui="browse-sort-controls">
    <div class="flex items-center gap-1.5">
        <span class="text-sm font-medium text-base-content">{{ __('ui.browse.sort_date') }}</span>
        <x-button
            type="button"
            wire:click="toggleSort('date')"
            wire:key="{{ $prefix }}-sort-date"
            class="btn-ghost btn-xs h-8 min-h-8 w-8 shrink-0 rounded-lg p-0 {{ $sort === 'date' ? 'text-neutral' : 'text-base-content/35 hover:text-base-content/70' }}"
            :title="__('ui.browse.sort_toggle_date')"
            :aria-label="__('ui.browse.sort_toggle_date')"
            aria-pressed="{{ $sort === 'date' ? 'true' : 'false' }}"
            data-ui="{{ $prefix }}-sort-date"
        >
            @if ($sort === 'date')
                @if ($dirAsc)
                    <x-icon name="o-chevron-up" />
                @else
                    <x-icon name="o-chevron-down" />
                @endif
            @else
                <x-icon name="o-arrows-up-down" />
            @endif
        </x-button>
    </div>

    <div class="flex items-center gap-1.5">
        <span class="text-sm font-medium text-base-content">{{ __('ui.browse.sort_name') }}</span>
        <x-button
            type="button"
            wire:click="toggleSort('name')"
            wire:key="{{ $prefix }}-sort-name"
            class="btn-ghost btn-xs h-8 min-h-8 w-8 shrink-0 rounded-lg p-0 {{ $sort === 'name' ? 'text-neutral' : 'text-base-content/35 hover:text-base-content/70' }}"
            :title="__('ui.browse.sort_toggle_name')"
            :aria-label="__('ui.browse.sort_toggle_name')"
            aria-pressed="{{ $sort === 'name' ? 'true' : 'false' }}"
            data-ui="{{ $prefix }}-sort-name"
        >
            @if ($sort === 'name')
                @if ($dirAsc)
                    <x-icon name="o-chevron-up" />
                @else
                    <x-icon name="o-chevron-down" />
                @endif
            @else
                <x-icon name="o-chevron-up-down" />
            @endif
        </x-button>
    </div>
</div>
