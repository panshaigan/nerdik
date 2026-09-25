<div class="p-1">
    <x-page-header :title="__('ui.dashboard.title')"/>
    <div class="max-w-7xl mx-auto space-y-8 sm:px-6 lg:px-8">
        <section class="space-y-4">
            @php
                $now = now();
                $autoOpenDone = false;
            @endphp
            @if ($feedHourGroups->isEmpty())
                <p class="text-sm opacity-70">{{ __('ui.dashboard.empty') }}</p>
            @else
                <div class="relative min-h-[12rem]">
                    <x-ui.livewire-loading-overlay
                        target="previousPage,nextPage,gotoPage"
                        data-ui="dashboard-feed-loading"
                    />
                    <ul class="space-y-6">
                        @foreach ($feedHourGroups as $group)
                            @php
                                $groupStartsAt = $group['starts_at'] ?? null;
                                $groupItems = $group['items'] ?? collect();
                                $shouldAutoOpen = ! $autoOpenDone
                                    && $groupStartsAt !== null
                                    && $groupItems->isNotEmpty()
                                    && $groupStartsAt->gte($now);
                                if ($shouldAutoOpen) {
                                    $autoOpenDone = true;
                                }
                            @endphp
                            <li class="list-none">
                                @if ($groupItems->isNotEmpty())
                                    <x-collapse
                                        class="ui-timeline-collapse ui-dashboard-feed-collapse"
                                        :data-ui="$groupStartsAt ? 'dashboard-feed-group-'.$groupStartsAt->getTimestamp() : 'dashboard-feed-group-no-time'"
                                        separator
                                        :open="$shouldAutoOpen"
                                    >
                                        <x-slot:heading>
                                            <span class="mb-2 text-xs font-semibold uppercase tracking-wide text-base-content/55">{{ $group['label'] }}</span>
                                        </x-slot:heading>
                                        <x-slot:content class="ui-dashboard-feed-collapse-content">
                                            <div class="ui-dashboard-feed-listings grid grid-cols-1 gap-8 md:grid-cols-3">
                                                @foreach ($groupItems as $row)
                                                    <x-cards.listing-card
                                                        :listing="$row['kind'] === 'event' ? $row['event'] : $row['activity']"
                                                        :interested-ids="$row['kind'] === 'event' ? ($interestedEventIds ?? []) : ($interestedActivityIds ?? [])"
                                                        :return-url="$browsingReturnUrl"
                                                    />
                                                @endforeach
                                            </div>
                                        </x-slot:content>
                                    </x-collapse>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>

                @if ($feedHourGroups->hasPages())
                    <div class="mt-5" data-ui="dashboard-feed-pagination">
                        {{ $feedHourGroups->links() }}
                    </div>
                @endif
            @endif
        </section>
    </div>

    @include('livewire.partials.listing-preview-modals')
</div>
