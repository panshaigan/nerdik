@php
    use App\Support\Browse\BrowseSearchUrl;
@endphp

<div class="pt-4 pb-12 px-1">
    <div class="ui-filter-form-events mx-auto w-full max-w-7xl space-y-6 mt-6 sm:px-6 lg:px-8">
        @include('livewire.catalog.partials.catalog-toolbar', [
            'placeholder' => __('ui.catalog.search_organizations_placeholder'),
        ])

        <div class="relative min-h-[12rem]">
            <x-ui.livewire-loading-overlay
                target="previousPage,nextPage,gotoPage,q"
                data-ui="catalog-organizations-loading"
            />
            <div
                class="ui-browse-events-listings grid grid-cols-1 gap-4 md:grid-cols-3 md:gap-6"
                data-ui="catalog-organizations-listings"
            >
                @forelse ($organizations as $organization)
                    <div wire:key="catalog-organization-{{ $organization->id }}" class="contents">
                        <x-catalog.catalog-card
                            :href="BrowseSearchUrl::forOrganization($organization)"
                            :title="$organization->name"
                            :subtitle="$organization->acronym"
                            :image-url="$organization->logoUrl()"
                            data-ui="catalog-organization-card"
                        />
                    </div>
                @empty
                    <div class="col-span-full rounded-xl border border-base-300 bg-base-100 p-6 text-center opacity-80">
                        {{ __('ui.catalog.empty_organizations') }}
                    </div>
                @endforelse
            </div>
        </div>

        @if ($organizations->hasPages())
            <div class="ui-browse-events-pagination mx-auto rounded-xl max-w-5xl p-4 mt-10" data-ui="catalog-organizations-pagination">
                {{ $organizations->links() }}
            </div>
        @endif
    </div>
</div>
