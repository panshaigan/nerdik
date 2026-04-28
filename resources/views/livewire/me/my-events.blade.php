<div class="py-12">
    <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
        <div class="flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-end sm:justify-between">
            <x-input
                id="me-events-q"
                wire:model.live.debounce.300ms="q"
                type="search"
                :label="__('ui.me.filter_by_name')"
                class="ui-field ui-me-events-search w-full min-w-0 max-w-md"
                :omit-error="true"
                data-ui="me-events-search"
            />
            <div class="flex shrink-0 justify-end">
                @include('livewire.browse.partials.sort-controls', ['sortIdPrefix' => 'me-events'])
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
            @forelse ($events as $event)
                <x-cards.event-card
                    :event="$event"
                    :interested-event-ids="$interestedEventIds ?? []"
                    :participating-event-ids="$participatingEventIds ?? []"
                />
            @empty
                <div class="col-span-full rounded-xl border border-base-300 bg-base-100 p-6 text-center opacity-80">
                    {{ __('ui.me.no_events') }}
                </div>
            @endforelse
        </div>

        @if ($events->hasPages())
            <div class="rounded-xl border border-base-300 bg-base-100 p-4">{{ $events->links() }}</div>
        @endif
    </div>
</div>
