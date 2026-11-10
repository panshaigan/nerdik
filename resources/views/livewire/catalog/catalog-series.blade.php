<div class="pt-4 pb-12 px-4 nav:px-6 lg:px-8">
    <div class="ui-filter-form-events w-full space-y-6 mt-6">
        <div
            class="ui-filter-form ui-browse-events-filter-shell mb-10"
            data-ui="catalog-toolbar"
        >
            <div
                class="flex flex-col items-stretch gap-3 sm:flex-row sm:items-center sm:gap-4"
                data-ui="catalog-series-toolbar"
            >
                <div class="ui-browse-events-toolbar-search min-w-0 w-full sm:flex-1">
                    <x-input
                        wire:model.live.debounce.300ms="q"
                        :placeholder="__('ui.catalog.search_series_placeholder')"
                        type="search"
                        :omit-error="true"
                        class="ui-field w-full"
                        data-ui="catalog-search"
                    />
                </div>
                <div
                    class="flex shrink-0 flex-wrap gap-2"
                    data-ui="catalog-series-kind-tabs"
                    role="tablist"
                >
                    <button
                        type="button"
                        wire:click="$set('kind', 'events')"
                        @class([
                            'btn btn-sm',
                            'btn-primary' => $kind === 'events',
                            'btn-ghost' => $kind !== 'events',
                        ])
                        role="tab"
                        aria-selected="{{ $kind === 'events' ? 'true' : 'false' }}"
                        data-ui="catalog-series-tab-events"
                    >
                        {{ __('ui.catalog.tab_event_series') }}
                    </button>
                    <button
                        type="button"
                        wire:click="$set('kind', 'activities')"
                        @class([
                            'btn btn-sm',
                            'btn-primary' => $kind === 'activities',
                            'btn-ghost' => $kind !== 'activities',
                        ])
                        role="tab"
                        aria-selected="{{ $kind === 'activities' ? 'true' : 'false' }}"
                        data-ui="catalog-series-tab-activities"
                    >
                        {{ __('ui.catalog.tab_activity_series') }}
                    </button>
                </div>
            </div>
        </div>

        <div class="relative min-h-[12rem]">
            <x-ui.livewire-loading-overlay
                target="previousPage,nextPage,gotoPage,q,kind"
                data-ui="catalog-series-loading"
            />
            <div
                class="ui-browse-events-listings grid grid-cols-1 gap-4 md:grid-cols-2 md:gap-6 xl:grid-cols-3"
                data-ui="catalog-series-listings"
            >
                @forelse ($seriesList as $series)
                    @php
                        $isActivityKind = $kind === 'activities';
                        $showRoute = $isActivityKind
                            ? route('activity-series.show', $series)
                            : route('event-series.show', $series);
                        $upcomingCount = $isActivityKind
                            ? (int) ($series->upcoming_public_activities_count ?? 0)
                            : (int) ($series->upcoming_public_events_count ?? 0);
                        $subtitle = $isActivityKind
                            ? trans_choice('ui.catalog.upcoming_activities', $upcomingCount, ['count' => $upcomingCount])
                            : trans_choice('ui.catalog.upcoming_events', $upcomingCount, ['count' => $upcomingCount]);
                        $typeBadge = $isActivityKind
                            ? __('ui.browse.activity_series')
                            : __('ui.browse.event_series');
                    @endphp
                    <div wire:key="catalog-series-{{ $kind }}-{{ $series->id }}" class="contents">
                        <x-catalog.catalog-card
                            :href="$showRoute"
                            :title="$series->name"
                            :subtitle="$subtitle"
                            :detail="$typeBadge"
                            icon="o-rectangle-stack"
                            :cover-picture="$seriesCoverPicturesById[(int) $series->id] ?? null"
                            data-ui="catalog-series-card"
                        />
                    </div>
                @empty
                    <div class="col-span-full rounded-xl border border-base-300 bg-base-100 p-6 text-center opacity-80">
                        {{ $kind === 'activities' ? __('ui.catalog.empty_activity_series') : __('ui.catalog.empty_series') }}
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
