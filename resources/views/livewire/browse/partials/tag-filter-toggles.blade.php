{{-- Tag match mode (+ optional past events). Pair with tag search partial below. --}}
<div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-base-content/80" data-ui="browse-tag-filter-toggles">
    <span class="whitespace-nowrap">{{ __('ui.browse.tags_match_any') }}</span>
    <input
        type="checkbox"
        wire:model.live="tags_match_all"
        class="toggle toggle-primary toggle-sm"
        data-ui="browse-tag-filter-match-mode"
    />
    <span class="whitespace-nowrap">{{ __('ui.browse.tags_match_all') }}</span>
    @if (! empty($includePastEventsToggle))
        <span class="mx-0.5 hidden text-base-content/30 sm:inline" aria-hidden="true">|</span>
        <span class="whitespace-nowrap" title="{{ __('ui.browse.include_past_events_hint') }}">{{ __('ui.browse.include_past_events') }}</span>
        <input
            type="checkbox"
            wire:model.live="include_past_events"
            class="toggle toggle-primary toggle-sm"
            data-ui="browse-include-past-events"
        />
    @endif
</div>
