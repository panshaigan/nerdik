<section class="mt-10">
    <div class="mb-5 flex items-end justify-between gap-4">
        <div>
            <h2 class="text-2xl font-semibold md:text-3xl">{{ __('ui.welcome.closest_heading') }}</h2>
        </div>
        <a href="{{ route('search.index') }}" class="btn btn-outline text-glow-base-100">
            {{ __('ui.welcome.open_full_calendar') }}
        </a>
    </div>

    @if (($upcomingListings ?? collect())->isNotEmpty())
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($upcomingListings as $listing)
                <a
                    href="{{ $listing->detailsUrl }}"
                    class="group rounded-2xl border border-base-300 bg-base-100 p-3 shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg"
                >
                    <div class="relative aspect-video overflow-hidden rounded-xl">
                        <x-listing-card-picture :picture="$listing->coverPicture" class="h-full w-full object-cover transition duration-500 group-hover:scale-[1.03]" />
                        <span class="absolute left-2 top-2 rounded-md bg-base-100/85 px-2 py-1 text-xs font-semibold">
                            {{ $listing->kindCornerLabel }}
                        </span>
                    </div>

                    <div class="px-1 pb-1 pt-3">
                        <h3 class="line-clamp-2 text-lg font-semibold leading-snug">{{ $listing->name }}</h3>
                        @if ($listing->timeSummary !== '')
                            <p class="mt-2 text-sm opacity-80">{{ $listing->timeSummary }}</p>
                        @endif
                        @if ($listing->locationSummary !== '')
                            <p class="mt-1 text-sm opacity-70">{{ $listing->locationSummary }}</p>
                        @endif
                        @if ($listing->badgeItems !== [])
                            <div class="pt-2">
                                <x-ui.activity-badge-group
                                    :items="$listing->badgeItems"
                                    class="!my-0 gap-2"
                                    :data-ui="$listing->badgeGroupDataUi"
                                />
                            </div>
                        @endif
                    </div>
                </a>
            @endforeach
        </div>
    @else
        <div class="rounded-2xl border border-dashed border-base-300 bg-base-100 p-6 text-center">
            <p class="text-lg font-medium">{{ __('ui.welcome.empty_title') }}</p>
            <p class="mt-2 text-sm opacity-70">{{ __('ui.welcome.empty_description') }}</p>
            <x-button :link="route('search.index')" class="btn-primary mt-4">{{ __('ui.welcome.browse_listings') }}</x-button>
        </div>
    @endif
</section>
