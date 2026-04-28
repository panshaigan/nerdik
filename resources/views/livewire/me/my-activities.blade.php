<div class="py-12">
    <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
        <div class="flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-end sm:justify-between">
            <x-input
                id="me-activities-q"
                wire:model.live.debounce.300ms="q"
                type="search"
                :label="__('ui.me.filter_by_name')"
                class="ui-field ui-me-activities-search w-full min-w-0 max-w-md"
                :omit-error="true"
                data-ui="me-activities-search"
            />
            <div class="flex shrink-0 justify-end">
                @include('livewire.browse.partials.sort-controls', ['sortIdPrefix' => 'me-activities'])
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
            @forelse ($activities as $activity)
                <x-cards.activity-card
                    :activity="$activity"
                    :interested-activity-ids="$interestedActivityIds ?? []"
                    :participating-activity-ids="$participatingActivityIds ?? []"
                />
            @empty
                <div class="col-span-full rounded-xl border border-base-300 bg-base-100 p-6 text-center opacity-80">
                    {{ __('ui.me.no_activities_owned') }}
                </div>
            @endforelse
        </div>

        @if ($activities->hasPages())
            <div class="rounded-xl border border-base-300 bg-base-100 p-4">{{ $activities->links() }}</div>
        @endif
    </div>
</div>
