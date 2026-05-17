<div class="py-12">
    <div class="max-w-7xl mx-auto space-y-8 sm:px-6 lg:px-8">
        <section class="space-y-4">
            <div class="rounded-2xl border border-base-300 bg-base-100/90 p-6 shadow-xl">
                <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                    <x-stat
                        title="{{ __('Hosted activities') }}"
                        value="{{ $hostedActivitiesCount }}"
                        icon="o-envelope"
                        color="text-primary"
                        class="bg-primary/15"
                    />
                    <x-stat
                        title="{{ __('Hosted events') }}"
                        value="{{ $hostedEventsCount }}"
                        icon="o-envelope"
                        color="text-secondary"
                        class="bg-secondary/15"
                    />
                    <x-stat
                        title="{{ __('Activities I took part in') }}"
                        value="{{ $participatedActivitiesCount }}"
                        icon="o-envelope"
                        color="text-accent"
                        class="bg-accent/15"
                    />
                </div>
            </div>
        </section>

        <section class="space-y-4">
            <h3 class="mb-4 text-lg font-medium text-base-content">{{ __('Incomming...') }}</h3>

            @if ($feed->isEmpty())
                <p class="text-sm opacity-70">{{ __('No upcoming events or activities yet.') }}</p>
            @else
                <div class="grid grid-cols-1 gap-8 md:grid-cols-2 xl:grid-cols-3">
                    @foreach ($feed as $row)
                        <x-cards.listing-card
                            :listing="$row['kind'] === 'event' ? $row['event'] : $row['activity']"
                            :interested-ids="$row['kind'] === 'event' ? ($interestedEventIds ?? []) : ($interestedActivityIds ?? [])"
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
