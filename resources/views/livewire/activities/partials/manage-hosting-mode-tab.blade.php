<div class="mt-4 border-t border-base-300 pt-4 space-y-3">
    <p class="fieldset-legend mb-0.5 font-medium">{{ __('ui.activities.hosting_mode_label') }}</p>
    @if ($hosting_mode === \App\Models\Activity::HOSTING_MODE_SCHEDULED_ON_EVENT)
        <p class="text-sm text-base-content/70">{{ __('ui.activities.hosting_mode_locked_scheduled') }}</p>
    @else
        <x-select
            id="hosting_mode"
            wire:model.live="hosting_mode"
            :label="__('ui.activities.hosting_mode_label')"
            error-field="hosting_mode"
            :options="[
                ['id' => \App\Models\Activity::HOSTING_MODE_DRAFT, 'name' => __('ui.activities.hosting_modes.draft')],
                ['id' => \App\Models\Activity::HOSTING_MODE_SELF_HOSTED, 'name' => __('ui.activities.hosting_modes.self_hosted')],
                ['id' => \App\Models\Activity::HOSTING_MODE_PROPOSED_TO_EVENT, 'name' => __('ui.activities.hosting_modes.proposed_to_event')],
            ]"
        />
    @endif

    @if ($hosting_mode === \App\Models\Activity::HOSTING_MODE_SELF_HOSTED)
        <div
            data-selfhost-map-wrap
            data-selfhost-room-root
            data-selfhost-rooms-url-template="{{ $roomsFetchUrlTemplate }}"
        >
            <p class="fieldset-legend font-medium text-base-content">{{ __('ui.activities.self_hosted_place') }}</p>
            <p class="mb-3 text-sm text-base-content/80">{{ __('ui.activities.self_hosted_place_help') }}</p>
            <div id="ui-activity-selfhost-places-section" data-event-places-unified class="space-y-3" wire:ignore>
                <script type="application/json" data-ep-config>@json($selfHostedPlacesConfig)</script>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <div class="relative z-[1000]">
                        <x-input
                            type="search"
                            data-ep-search
                            autocomplete="off"
                            :label="__('ui.activities.self_hosted_place')"
                            :placeholder="__('ui.activities.self_hosted_place_search_placeholder')"
                            class="w-full"
                            :omit-error="true"
                        />
                        <div data-ep-results class="absolute left-0 right-0 top-full z-[1001] mt-1 hidden max-h-60 overflow-y-auto rounded-lg border border-base-300 bg-base-100 py-1 shadow-lg"></div>
                        <x-field-error :messages="$errors->get('self_hosted_venue_place_id')" class="mt-2" />
                    </div>
                    <div class="relative overflow-visible">
                        <x-input
                            id="self_hosted_room_name"
                            wire:model="self_hosted_room_name"
                            :label="__('ui.slots.room_optional')"
                            error-field="self_hosted_room_name"
                            autocomplete="off"
                            :placeholder="__('ui.slots.room_placeholder')"
                            data-selfhost-room-input
                            aria-autocomplete="list"
                            aria-expanded="false"
                            aria-controls="selfhost-room-suggestions-popup"
                        />
                        <div
                            id="selfhost-room-suggestions-popup"
                            class="fixed z-[9999] hidden max-h-[min(14rem,50vh)] overflow-y-auto rounded-lg border border-base-300 bg-base-100 py-1 shadow-lg"
                            data-selfhost-room-popup
                            role="listbox"
                        ></div>
                    </div>
                    <div class="">
                        <x-input
                            id="self_hosted_starts_at"
                            :label="__('ui.activities.self_hosted_starts_at')"
                            wire:model="self_hosted_starts_at"
                            type="datetime-local"
                            :min="$selfHostedStartTimeMin"
                            error-field="self_hosted_starts_at"
                            data-selfhost-start-input
                        />
                    </div>
                </div>
                <div data-ep-map class="z-0 w-full overflow-hidden rounded-md border border-base-300 bg-base-200/30" style="min-height: 280px; height: min(420px, 50vh);"></div>
                <div data-ep-chips class="flex min-h-[1.5rem] flex-wrap gap-2"></div>
                <div data-ep-new-venues-wrap class="{{ count($selfHostedPlacesConfig['initialNewPlaces'] ?? []) ? '' : 'hidden' }} space-y-2 rounded-lg border border-warning/30 bg-warning/5 p-3">
                    <p class="text-xs font-medium text-base-content" data-ep-new-heading>{{ __('ui.activities.self_hosted_new_venues_label') }}</p>
                    <div data-ep-new-venues class="space-y-3"></div>
                </div>
                <div data-ep-place-ids></div>
            </div>
        </div>
    @endif
</div>

@if ($hosting_mode === \App\Models\Activity::HOSTING_MODE_PROPOSED_TO_EVENT)
    <div class="mt-4 border-t border-base-300 pt-4">
        <p class="fieldset-legend mb-0.5 font-medium">{{ __('ui.activities.propose_to_event') }}</p>
        <p class="mb-3 text-xs text-base-content/70">{{ __('ui.activities.propose_to_event_help') }}</p>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div>
                <div class="relative" data-proposal-event-autocomplete>
                    <x-input
                        id="proposal_event_search"
                        class="ui-field ui-field-proposal-event"
                        :label="__('ui.activities.proposal_event')"
                        wire:model.live.debounce.250ms="proposal_event_search"
                        error-field="proposal_event_id"
                        type="search"
                        autocomplete="off"
                        data-proposal-event-input
                        aria-autocomplete="list"
                        aria-expanded="false"
                        aria-controls="proposal-event-suggestions-popup"
                        :placeholder="__('ui.activities.proposal_event_search_placeholder')"
                        data-ui="proposal-event-search"
                    />
                    <input type="hidden" wire:model.live="proposal_event_id" data-proposal-event-id />
                    <script type="application/json" data-proposal-event-config>
                        @json([
                            'initialSuggestions' => $proposalEventSuggestions,
                            'noneLabel' => __('ui.activities.proposal_event_none'),
                            'noResultsLabel' => __('ui.activities.proposal_event_search_no_results')
                        ])
                    </script>
                    <div
                        id="proposal-event-suggestions-popup"
                        class="absolute left-0 right-0 z-20 mt-1 hidden max-h-56 overflow-y-auto rounded-lg border border-base-300 bg-base-100 py-1 shadow-lg"
                        data-proposal-event-popup
                        role="listbox"
                    ></div>
                </div>
                @if ($futureEvents->isEmpty())
                    <p class="mt-1 text-xs text-base-content/60">{{ __('ui.activities.proposal_no_future_events') }}</p>
                @endif
            </div>

            <div>
                <x-input
                    id="proposal_preferred_start_time"
                    class="ui-field ui-field-proposal-preferred-time w-full"
                    :label="__('ui.activities.proposal_preferred_start_time')"
                    wire:model="proposal_preferred_start_time"
                    type="datetime-local"
                    :min="$proposalPreferredStartTimeMin"
                    :max="$proposalPreferredStartTimeMax"
                    :disabled="$proposal_event_id === null"
                    error-field="proposal_preferred_start_time"
                    data-ui="proposal-preferred-time-input"
                />
                @if (! $proposal_event_id)
                    <p class="mt-1 text-xs text-base-content/60">{{ __('ui.activities.proposal_preferred_start_time_requires_event') }}</p>
                @endif
            </div>
        </div>

        @if ($proposal_event_id && $proposalEventSlots->isNotEmpty())
            <div class="mt-4 space-y-2 border-t border-base-300/50 pt-4">
                <x-proposals.preferred-slot-checklist
                    :slots="$proposalEventSlots"
                    wire-model="proposal_slot_ids"
                    error-field="proposal_slot_ids"
                />
            </div>
        @endif
    </div>
@endif
