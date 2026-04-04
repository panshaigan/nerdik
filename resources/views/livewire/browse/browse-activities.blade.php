<div class="py-12">
    <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
        <form
            id="ui-browse-activities-form"
            wire:submit.prevent="applySearch"
            class="ui-filter-form ui-filter-form-activities space-y-4"
            data-ui="browse-activities-form"
        >
            <div class="card border border-base-300 bg-base-100 p-4 shadow-sm" data-ui="browse-activities-filters-card">
                <div class="flex flex-wrap items-end gap-4">
                    <x-input
                        id="from_date"
                        wire:model.defer="from_date"
                        type="date"
                        :label="__('From')"
                        class="ui-field ui-field-from-date w-full max-w-[12rem]"
                        :omit-error="true"
                        data-ui="browse-activities-from-date-input"
                    />
                    <x-input
                        id="to_date"
                        wire:model.defer="to_date"
                        type="date"
                        :label="__('To')"
                        class="ui-field ui-field-to-date w-full max-w-[12rem]"
                        :omit-error="true"
                        data-ui="browse-activities-to-date-input"
                    />
                    <x-form-select id="place_id" wire:model.defer="place_id" :label="__('Place')" class="ui-field ui-field-place" data-ui="browse-activities-place-select">
                        <option value="">{{ __('Any') }}</option>
                        @foreach ($places as $place)
                            <option value="{{ $place->id }}">{{ $place->name }}</option>
                        @endforeach
                    </x-form-select>
                </div>

                <div class="mt-4 flex flex-col gap-4 border-t border-base-300 pt-4 lg:flex-row lg:items-start lg:gap-6">
                    <div class="min-w-0 flex-1 lg:max-w-xl">
                        @include('livewire.browse.partials.tag-filter')
                    </div>
                    <div class="flex w-full min-w-0 flex-wrap items-end gap-3 lg:ms-auto lg:w-auto lg:max-w-2xl lg:shrink-0 lg:justify-end">
                        <x-input
                            id="q"
                            wire:model.defer="q"
                            type="text"
                            :label="null"
                            :placeholder="__('Activity name…')"
                            class="ui-field ui-field-search min-w-[12rem] flex-1 sm:max-w-xs"
                            :omit-error="true"
                            data-ui="browse-activities-search-input"
                        />
                        <x-button id="ui-browse-activities-submit" type="submit" class="btn-primary ui-action ui-action-search shrink-0" data-ui="browse-activities-search-submit" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="applySearch">{{ __('Search') }}</span>
                            <span wire:loading wire:target="applySearch">{{ __('Searching…') }}</span>
                        </x-button>
                        @if ($this->hasActiveFilters())
                            <x-button id="ui-browse-activities-clear" type="button" wire:click="clearFilters" class="btn-ghost ui-action ui-action-clear shrink-0" data-ui="browse-activities-clear">{{ __('Clear') }}</x-button>
                        @endif
                    </div>
                </div>
            </div>
        </form>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
            @forelse ($activities as $activity)
                <x-cards.activity-card :activity="$activity" :wishlist-activity-ids="$wishlistActivityIds ?? []" />
            @empty
                <div class="col-span-full rounded-xl border border-base-300 bg-base-100 p-6 text-center opacity-80">
                    {{ __('No activities found.') }}
                </div>
            @endforelse
        </div>

        @if ($activities->hasPages())
            <div class="rounded-xl border border-base-300 bg-base-100 p-4">{{ $activities->links() }}</div>
        @endif
    </div>
</div>
