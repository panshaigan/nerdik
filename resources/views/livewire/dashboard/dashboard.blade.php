<div class="p-1">
    <x-page-header title="Dashboard"/>
    <div class="max-w-7xl mx-auto space-y-8 sm:px-6 lg:px-8">
        <section class="space-y-4">
            @if ($feed->isEmpty())
                <p class="text-sm opacity-70">{{ __('No upcoming events or activities yet.') }}</p>
            @else
                <div class="grid grid-cols-1 gap-8 md:grid-cols-2 xl:grid-cols-3">
                    @foreach ($feed as $row)
                        <x-cards.listing-card
                            :listing="$row['kind'] === 'event' ? $row['event'] : $row['activity']"
                            :interested-ids="$row['kind'] === 'event' ? ($interestedEventIds ?? []) : ($interestedActivityIds ?? [])"
                            :return-url="$browsingReturnUrl"
                        />
                    @endforeach
                </div>

                @if ($feed->hasPages())
                    <div class="mt-5">
                        {{ $feed->links() }}
                    </div>
                @endif
            @endif
        </section>
    </div>

    @include('livewire.partials.listing-preview-modals')
</div>
