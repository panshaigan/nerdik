@php
    $hasDateRange = filled($exceptionFrom ?? null) || filled($exceptionTo ?? null);
@endphp

<div
    data-browse-date-range
    data-from-prop="exceptionFrom"
    data-to-prop="exceptionTo"
    data-locale="{{ app()->getLocale() }}"
    data-from-date="{{ $exceptionFrom ?? '' }}"
    data-to-date="{{ $exceptionTo ?? '' }}"
    data-min-date="today"
    data-clear-label="{{ __('ui.browse.date_range_clear') }}"
    class="ui-availability-date-range w-full"
    data-ui="profile-availability-date-range"
>
    <label class="label">
        <span class="label-text font-medium">{{ __('ui.profile.availability.exception_range') }}</span>
    </label>
    <div class="relative">
        <input
            type="text"
            data-browse-date-range-input
            class="input input-bordered w-full cursor-pointer pr-10"
            readonly
            placeholder="{{ __('ui.profile.availability.exception_range_placeholder') }}"
            aria-label="{{ __('ui.profile.availability.exception_range') }}"
            value="{{ $hasDateRange ? (($exceptionFrom === $exceptionTo || blank($exceptionTo)) ? ($exceptionFrom ?? '') : ($exceptionFrom.' – '.$exceptionTo)) : '' }}"
        >
        <span class="pointer-events-none absolute inset-y-0 end-3 flex items-center text-base-content/50">
            <x-icon name="o-calendar-days" class="h-4 w-4" />
        </span>
    </div>
</div>
