<div class="pt-4 pb-12 px-1">
    <div class="ui-filter-form-events mx-auto w-full max-w-7xl space-y-6 mt-6 sm:px-6 lg:px-8">
        @include('livewire.catalog.partials.catalog-toolbar', [
            'placeholder' => __('ui.catalog.search_series_placeholder'),
        ])

        <div class="relative min-h-[12rem]">
            <x-ui.livewire-loading-overlay
                target="previousPage,nextPage,gotoPage,q"
                data-ui="catalog-series-loading"
            />
            <div
                class="ui-browse-events-listings grid grid-cols-1 gap-4 md:grid-cols-3 md:gap-6"
                data-ui="catalog-series-listings"
            >
                @forelse ($seriesList as $series)
                    <div wire:key="catalog-series-{{ $series->id }}" class="contents">
                        <x-catalog.catalog-card
                            :href="route('event-series.show', $series)"
                            :title="$series->name"
                            :subtitle="trans_choice('ui.catalog.upcoming_events', (int) $series->upcoming_public_events_count, [
                                'count' => (int) $series->upcoming_public_events_count,
                            ])"
                            icon="o-rectangle-stack"
                            data-ui="catalog-series-card"
                        />
                    </div>
                @empty
                    <div class="col-span-full rounded-xl border border-base-300 bg-base-100 p-6 text-center opacity-80">
                        {{ __('ui.catalog.empty_series') }}
                    </div>
                @endforelse
            </div>
        </div>

        @if ($seriesList->hasPages())
            <div class="ui-browse-events-pagination mx-auto rounded-xl max-w-5xl p-4 mt-10" data-ui="catalog-series-pagination">
                {{ $seriesList->links() }}
            </div>
        @endif
    </div>
</div>
