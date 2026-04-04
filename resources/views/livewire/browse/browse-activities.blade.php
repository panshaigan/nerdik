<div class="py-12">
    <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
        <div class="ui-filter-form ui-filter-form-activities space-y-4" data-ui="browse-activities-form">
            <div class="card border border-base-300 bg-base-100 p-4 shadow-sm" data-ui="browse-activities-filters-card">
                <div class="flex flex-wrap items-end gap-4">
                    <x-input
                        id="from_date"
                        wire:model.live.debounce.300ms="from_date"
                        type="date"
                        :label="__('From')"
                        class="ui-field ui-field-from-date w-full max-w-[12rem]"
                        :omit-error="true"
                        data-ui="browse-activities-from-date-input"
                    />
                    <x-input
                        id="to_date"
                        wire:model.live.debounce.300ms="to_date"
                        type="date"
                        :label="__('To')"
                        class="ui-field ui-field-to-date w-full max-w-[12rem]"
                        :omit-error="true"
                        data-ui="browse-activities-to-date-input"
                    />
                    <x-form-select id="place_id" wire:model.live="place_id" :label="__('Place')" class="ui-field ui-field-place" data-ui="browse-activities-place-select">
                        <option value="">{{ __('Any') }}</option>
                        @foreach ($places as $place)
                            <option value="{{ $place->id }}">{{ $place->name }}</option>
                        @endforeach
                    </x-form-select>
                </div>

                <div class="mt-4 flex flex-col gap-4 border-t border-base-300 pt-4 lg:flex-row lg:items-start lg:gap-4">
                    <div class="min-w-0 w-full flex-1 basis-0 lg:min-w-[min(100%,18rem)]">
                        @include('livewire.browse.partials.tag-filter')
                    </div>
                    @if ($this->hasActiveFilters())
                        <div class="flex w-full shrink-0 flex-wrap items-center gap-3 lg:w-auto lg:justify-end">
                            <x-button id="ui-browse-activities-clear" type="button" wire:click="clearFilters" class="btn-ghost ui-action ui-action-clear shrink-0" data-ui="browse-activities-clear">{{ __('Clear') }}</x-button>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            @include('livewire.browse.partials.sort-controls', ['sortIdPrefix' => 'browse-activities'])
        </div>

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
