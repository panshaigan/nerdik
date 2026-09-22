@php
    use App\Support\Browse\BrowseSearchUrl;
@endphp

<div class="pt-4 pb-12 px-1">
    <div class="ui-filter-form-events mx-auto w-full max-w-7xl space-y-6 mt-6 sm:px-6 lg:px-8">
        @include('livewire.catalog.partials.catalog-toolbar', [
            'placeholder' => __('ui.catalog.search_places_placeholder'),
        ])

        <div class="relative min-h-[12rem]">
            <x-ui.livewire-loading-overlay
                target="previousPage,nextPage,gotoPage,q"
                data-ui="catalog-places-loading"
            />
            <div
                class="ui-browse-events-listings grid grid-cols-1 gap-4 md:grid-cols-3 md:gap-6"
                data-ui="catalog-places-listings"
            >
                @forelse ($places as $place)
                    @php
                        $address = filled($place->address) ? trim((string) $place->address) : null;
                        $location = $place->locationLabel();
                    @endphp
                    <div wire:key="catalog-place-{{ $place->id }}" class="flex h-full flex-col gap-2">
                        <x-catalog.catalog-card
                            :href="BrowseSearchUrl::forPlace($place)"
                            :title="$place->name"
                            :subtitle="$address ?? (filled($location) ? $location : null)"
                            :detail="$address !== null && filled($location) ? $location : null"
                            icon="o-map-pin"
                            stacked
                            data-ui="catalog-place-card"
                        />
                        <x-ui.entity-links
                            :links="$place->links"
                            appearance="compact"
                            data-ui="catalog-place-entity-links"
                            class="px-1"
                        />
                    </div>
                @empty
                    <div class="col-span-full rounded-xl border border-base-300 bg-base-100 p-6 text-center opacity-80">
                        {{ __('ui.catalog.empty_places') }}
                    </div>
                @endforelse
            </div>
        </div>

        @if ($places->hasPages())
            <div class="ui-browse-events-pagination mx-auto rounded-xl max-w-5xl p-4 mt-10" data-ui="catalog-places-pagination">
                {{ $places->links() }}
            </div>
        @endif
    </div>
</div>
