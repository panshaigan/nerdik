{{-- Mutually exclusive: only one of only_events / only_activities, or neither for both. --}}
<div data-ui="browse-listing-type-filter">
    <div class="flex w-full flex-col gap-6 px-1 py-2">
        <section class="space-y-3" data-ui="browse-filters-section-where">
            <p class="text-xs font-semibold uppercase tracking-wide text-base-content/60">
                {{ __('ui.browse.filters_section_where') }}
            </p>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-select
                    id="browse_place_id"
                    wire:model.live="place_id"
                    :label="__('ui.browse.place')"
                    :options="$browsePlaceOptions ?? []"
                    :placeholder="__('ui.browse.any')"
                    placeholder-value=""
                    class="ui-field ui-field-place w-full"
                    data-ui="browse-place-select"
                    :omit-error="true"
                />
                <x-select
                    id="browse_organization_id"
                    wire:model.live="organization_id"
                    :label="__('ui.browse.organization')"
                    :options="$browseOrganizationOptions ?? []"
                    :placeholder="__('ui.browse.any')"
                    placeholder-value=""
                    class="ui-field ui-field-organization w-full"
                    data-ui="browse-organization-select"
                    :omit-error="true"
                />
            </div>
        </section>

        <hr class="border-base-content/10" />

        <section class="space-y-3" data-ui="browse-filters-section-show">
            <p class="text-xs font-semibold uppercase tracking-wide text-base-content/60">
                {{ __('ui.browse.filters_section_show') }}
            </p>
            <div class="flex flex-col gap-3">
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
                <x-checkbox
                    wire:model.live="only_free_places"
                    :label="__('ui.browse.only_free_places')"
                    data-ui="browse-only-free-places"
                />
                @auth
                    <x-checkbox
                        wire:model.live="only_mine"
                        :label="__('ui.browse.only_mine')"
                        data-ui="browse-only-mine"
                    />
                @endauth
            </div>
        </section>

        <hr class="border-base-content/10" />

        <section class="space-y-3" data-ui="browse-filters-section-tags">
            <p class="text-xs font-semibold uppercase tracking-wide text-base-content/60">
                {{ __('ui.browse.filters_section_tags') }}
            </p>
            <x-group
                wire:model.live="tags_match_all"
                data-ui="browse-tag-filter-match-mode"
                class="ui-browse-filter-toggle rounded-2xl btn-sm w-fit"
                :options="[
                    ['id' => 0, 'name' => __('ui.browse.tags_match_any')],
                    ['id' => 1, 'name' => __('ui.browse.tags_match_all')],
                ]"
            />
        </section>

        <hr class="border-base-content/10" />

        <footer
            class="browse-events-save-clear-toolbar flex flex-wrap items-center justify-between gap-3"
            data-ui="browse-events-save-clear-toolbar"
        >
            <div class="flex flex-wrap items-center gap-2">
                @auth
                    <x-button
                        type="button"
                        wire:click="applyMyPreferences"
                        wire:key="browse-events-apply-preferences"
                        class="btn btn-ghost btn-sm"
                        :aria-label="__('ui.browse.apply_preferences')"
                        data-ui="browse-events-apply-preferences"
                    >
                        <x-icon name="o-sparkles" class="h-4 w-4 shrink-0" />
                        {{ __('ui.browse.apply_preferences') }}
                    </x-button>
                @endauth
                <x-button
                    type="button"
                    wire:click="saveSearchParams"
                    wire:key="browse-events-save-search"
                    class="btn btn-ghost btn-sm"
                    :aria-label="__('ui.browse.save_search')"
                    data-ui="browse-events-save-search"
                >
                    <x-icon name="o-bookmark" class="h-4 w-4 shrink-0" />
                    {{ __('ui.browse.save_search') }}
                </x-button>
            </div>
            <x-button
                type="button"
                wire:click="clearFilters"
                wire:key="browse-events-clear"
                wire:loading.attr="disabled"
                class="btn btn-ghost btn-sm"
                :aria-label="__('ui.browse.clear')"
                data-ui="browse-events-clear"
            >
                <x-icon name="o-x-mark" class="h-4 w-4 shrink-0" />
                {{ __('ui.browse.clear') }}
            </x-button>
        </footer>
    </div>
</div>
