<div class="pt-4 pb-12 px-4 nav:px-6 lg:px-8">
    <div class="ui-filter-form-events w-full space-y-6 mt-6">
        @include('livewire.catalog.partials.catalog-series-toolbar')

        <div class="relative min-h-[12rem]">
            <x-ui.livewire-loading-overlay
                target="previousPage,nextPage,gotoPage,q,sort,sort_dir,toggleSort"
                data-ui="catalog-series-loading"
            />
            <div
                class="ui-browse-events-listings grid grid-cols-1 gap-4 md:grid-cols-2 md:gap-6 xl:grid-cols-3"
                data-ui="catalog-series-listings"
            >
                @forelse ($seriesList as $listing)
                    @php
                        $isActivity = ($listing['kind'] ?? null) === 'activity';
                        $series = $isActivity
                            ? ($listing['activitySeries'] ?? null)
                            : ($listing['eventSeries'] ?? null);
                    @endphp
                    @continue($series === null)
                    @php
                        $showRoute = $isActivity
                            ? route('activity-series.show', $series)
                            : route('event-series.show', $series);
                        $upcomingCount = $isActivity
                            ? (int) ($series->upcoming_public_activities_count ?? 0)
                            : (int) ($series->upcoming_public_events_count ?? 0);
                        $subtitle = $isActivity
                            ? trans_choice('ui.catalog.upcoming_activities', $upcomingCount, ['count' => $upcomingCount])
                            : trans_choice('ui.catalog.upcoming_events', $upcomingCount, ['count' => $upcomingCount]);
                        $typeBadge = $isActivity
                            ? __('ui.browse.activity_series')
                            : __('ui.browse.event_series');
                        $coverPicture = $isActivity
                            ? ($activitySeriesCoverPicturesById[(int) $series->id] ?? null)
                            : ($eventSeriesCoverPicturesById[(int) $series->id] ?? null);
                    @endphp
                    <div wire:key="catalog-series-{{ $listing['kind'] }}-{{ $series->id }}" class="contents">
                        <x-catalog.catalog-card
                            :href="$showRoute"
                            :title="$series->name"
                            :subtitle="$subtitle"
                            :detail="$typeBadge"
                            icon="o-rectangle-stack"
                            :cover-picture="$coverPicture"
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
