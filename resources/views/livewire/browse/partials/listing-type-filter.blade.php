{{-- Mutually exclusive: only one of only_events / only_activities, or neither for both. --}}
<div data-ui="browse-listing-type-filter">
        <div class="w-64 space-y-2 p-3">
            <x-checkbox
                wire:model.live="include_past_events"
                :label="__('ui.browse.include_past_events')"
                data-ui="browse-include-past-events"
            />
            <x-checkbox
                wire:model.live="only_events"
                :label="__('ui.browse.only_events')"
                data-ui="browse-only-events"
            />
            <x-checkbox
                wire:model.live="only_activities"
                :label="__('ui.browse.only_activities')"
                data-ui="browse-only-activities"
            />
        </div>
</div>
