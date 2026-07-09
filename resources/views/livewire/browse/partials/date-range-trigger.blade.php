@php
    $hasDateRange = filled($from_date ?? null) || filled($to_date ?? null);
@endphp

<div
    data-browse-date-range
    data-locale="{{ app()->getLocale() }}"
    data-from-date="{{ $from_date ?? '' }}"
    data-to-date="{{ $to_date ?? '' }}"
    data-clear-label="{{ __('ui.browse.date_range_clear') }}"
    class="ui-browse-date-range shrink-0 self-center"
    data-ui="browse-date-range"
>
    <input
        type="text"
        data-browse-date-range-input
        class="sr-only"
        tabindex="-1"
        aria-hidden="true"
    >
    <button
        type="button"
        data-browse-date-range-trigger
        @class([
            'btn btn-ghost btn-sm btn-square rounded-xl ui-browse-date-range-trigger',
            'is-active' => $hasDateRange,
        ])
        title="{{ __('ui.browse.date_range_toggle') }}"
        aria-label="{{ __('ui.browse.date_range_toggle') }}"
        aria-pressed="{{ $hasDateRange ? 'true' : 'false' }}"
        data-ui="browse-date-range-trigger"
    >
        <x-icon name="o-calendar-days" class="h-4 w-4 shrink-0" />
    </button>
</div>
