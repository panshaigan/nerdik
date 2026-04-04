@php
    $prefix = $sortIdPrefix ?? 'browse';
    $dirAsc = strtolower($sort_dir ?? 'asc') === 'asc';
@endphp
<div class="flex flex-wrap items-center gap-x-6 gap-y-2" data-ui="browse-sort-controls">
    <div class="flex items-center gap-1.5">
        <span class="text-sm font-medium text-base-content">{{ __('ui.browse.sort_date') }}</span>
        <button
            type="button"
            wire:click="toggleSort('date')"
            wire:key="{{ $prefix }}-sort-date"
            class="btn btn-ghost btn-xs h-8 min-h-8 w-8 shrink-0 rounded-lg p-0 {{ $sort === 'date' ? 'text-primary' : 'text-base-content/35 hover:text-base-content/70' }}"
            title="{{ __('ui.browse.sort_toggle_date') }}"
            aria-label="{{ __('ui.browse.sort_toggle_date') }}"
            aria-pressed="{{ $sort === 'date' ? 'true' : 'false' }}"
            data-ui="{{ $prefix }}-sort-date"
        >
            @if ($sort === 'date')
                @if ($dirAsc)
                    {{-- chevron up --}}
                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 15.75l7.5-7.5 7.5 7.5" />
                    </svg>
                @else
                    {{-- chevron down --}}
                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                    </svg>
                @endif
            @else
                {{-- inactive: up + down hint --}}
                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 10l4-4 4 4M8 14l4 4 4-4" />
                </svg>
            @endif
        </button>
    </div>

    <div class="flex items-center gap-1.5">
        <span class="text-sm font-medium text-base-content">{{ __('ui.browse.sort_name') }}</span>
        <button
            type="button"
            wire:click="toggleSort('name')"
            wire:key="{{ $prefix }}-sort-name"
            class="btn btn-ghost btn-xs h-8 min-h-8 w-8 shrink-0 rounded-lg p-0 {{ $sort === 'name' ? 'text-primary' : 'text-base-content/35 hover:text-base-content/70' }}"
            title="{{ __('ui.browse.sort_toggle_name') }}"
            aria-label="{{ __('ui.browse.sort_toggle_name') }}"
            aria-pressed="{{ $sort === 'name' ? 'true' : 'false' }}"
            data-ui="{{ $prefix }}-sort-name"
        >
            @if ($sort === 'name')
                @if ($dirAsc)
                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 15.75l7.5-7.5 7.5 7.5" />
                    </svg>
                @else
                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                    </svg>
                @endif
            @else
                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 10l4-4 4 4M8 14l4 4 4-4" />
                </svg>
            @endif
        </button>
    </div>
</div>
