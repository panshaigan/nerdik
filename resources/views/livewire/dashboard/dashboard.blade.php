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
                                    :data-ui="$groupStartsAt ? 'dashboard-feed-group-'.$groupStartsAt->getTimestamp() : 'dashboard-feed-group-no-time'"
                                    separator
                                    :open="$shouldAutoOpen"
                                >
                                    <x-slot:heading>
                                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-base-content/55">
                                            {{ $group['label'] }}
                                        </p>
                                    </x-slot:heading>
                                    <x-slot:content>
                                        <div class="grid grid-cols-1 gap-8 md:grid-cols-2 xl:grid-cols-3">
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

                @if ($feedHourGroups->hasPages())
                    <div class="mt-5">
                        {{ $feedHourGroups->links() }}
                    </div>
                @endif
            @endif
        </section>
    </div>

    @include('livewire.partials.listing-preview-modals')
</div>
