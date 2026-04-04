{{-- Mutually exclusive: only one of only_events / only_activities, or neither for both. --}}
<div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-base-content/80" data-ui="browse-listing-type-filter">
    <label class="flex cursor-pointer items-center gap-2">
        <input
            type="checkbox"
            wire:model.live="only_events"
            class="checkbox checkbox-sm checkbox-primary"
            data-ui="browse-only-events"
        />
        <span>{{ __('ui.browse.only_events') }}</span>
    </label>
    <label class="flex cursor-pointer items-center gap-2">
        <input
            type="checkbox"
            wire:model.live="only_activities"
            class="checkbox checkbox-sm checkbox-primary"
            data-ui="browse-only-activities"
        />
        <span>{{ __('ui.browse.only_activities') }}</span>
    </label>
</div>
