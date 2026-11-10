<div class="p-1">
    <x-page-header :title="__('ui.dashboard.title')"/>
    <div class="max-w-7xl mx-auto space-y-8 sm:px-6 lg:px-8">
        @if ($platformStats !== null && $platformStatsUrls !== null)
            @include('livewire.dashboard.partials.admin-platform-stats', [
                'stats' => $platformStats,
                'urls' => $platformStatsUrls,
            ])
        @endif

        @include('livewire.dashboard.partials.personal-activity-stats', [
            'upcomingCreatedActivitiesCount' => $upcomingCreatedActivitiesCount,
            'upcomingParticipatingActivitiesCount' => $upcomingParticipatingActivitiesCount,
            'upcomingInterestedActivitiesCount' => $upcomingInterestedActivitiesCount,
            'userGroupsCount' => $userGroupsCount,
        ])

        @include('livewire.dashboard.partials.dashboard-toolbar', [
            'feedIsEmpty' => $feedIsEmpty,
            'groupsPanelOpen' => $groupsPanelOpen,
            'dashboardGroupsStrip' => $dashboardGroupsStrip,
        ])

        @include('livewire.dashboard.partials.this-week', [
            'thisWeekItems' => $thisWeekItems,
            'browsingReturnUrl' => $browsingReturnUrl,
            'interestedEventIds' => $interestedEventIds,
            'interestedActivityIds' => $interestedActivityIds,
            'eventProgrammeActivityCounts' => $eventProgrammeActivityCounts,
        ])

        @include('livewire.dashboard.partials.needs-action', [
            'actionItems' => $dashboardActionItems,
        ])

        <section id="dashboard-feed" class="space-y-4 scroll-mt-24">
            @php
                $now = now();
                $autoOpenDone = false;
            @endphp
            @if ($feedIsEmpty)
                <p class="text-sm opacity-70">{{ __('ui.dashboard.empty') }}</p>
            @else
                <p class="text-xs font-semibold uppercase tracking-wide text-base-content/55">
                    {{ __('ui.dashboard.upcoming') }}
                </p>
                <div class="relative min-h-[12rem]">
                    <x-ui.livewire-loading-overlay
                        target="previousPage,nextPage,gotoPage"
                        data-ui="dashboard-feed-loading"
                    />
                    <ul class="space-y-6">
                        @foreach ($feedMonthGroups as $group)
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
                                                        :confirmed-activities-count="$row['kind'] === 'event' ? ($eventProgrammeActivityCounts[$row['event']->id] ?? 0) : null"
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

                @if ($feedMonthGroups->hasPages())
                    <div class="mt-5" data-ui="dashboard-feed-pagination">
                        {{ $feedMonthGroups->links() }}
                    </div>
                @endif
            @endif
        </section>
    </div>

    @include('livewire.partials.listing-preview-modals')
</div>
