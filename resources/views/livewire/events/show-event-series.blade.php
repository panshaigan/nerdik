<div class="relative" data-ui="event-series-show" data-show-event-series-id="{{ $series->id }}">
    <div class="relative z-0 space-y-4 sm:space-y-6">
        <x-page-header :title="$series->name">
            <x-slot:subtitle>
                <div>{{ __('ui.event_series.show_subtitle') }}</div>
            </x-slot:subtitle>
        </x-page-header>

        <div class="ui-content-card relative rounded-2xl mb-4 md:mb-6">
            <x-ui.tabs-with-toolbar
                wire:model.live="tab"
                label-div-class="flex gap-5 overflow-x-auto px-3 pt-1"
                label-class="tab tab-lifted tab-md !px-0 !py-2 pb-2 text-sm font-semibold text-base-content/70 hover:text-base-content"
                active-class="!text-base-content border-b border-primary text-primary"
                tabs-class="w-full"
                toolbar-wrapper-class="flex shrink-0 items-center gap-1 px-2 sm:px-3"
                data-ui="event-series-show-tabs"
            >
                <x-tab name="events" :label="__('ui.event_series.tab_events')" class="p-4 sm:p-6" data-ui="event-series-tab-events" icon="o-calendar-days">
                    @if ($events->isEmpty())
                        <p class="text-base-content/70">{{ __('ui.event_series.empty_events') }}</p>
                    @else
                        <ul class="divide-y divide-base-300" data-ui="event-series-editions-list">
                            @foreach ($events as $edition)
                                <li
                                    @class([
                                        'flex flex-wrap items-center justify-between gap-3 py-3',
                                        'opacity-50' => $edition->isCancelled(),
                                    ])
                                    data-ui="event-series-edition"
                                >
                                    <div class="min-w-0 flex-1">
                                        <a
                                            href="{{ route('events.show', $edition) }}"
                                            wire:navigate
                                            class="link link-hover font-semibold break-words"
                                            data-ui="event-series-edition-link"
                                        >{{ $edition->name }}</a>
                                        <div class="mt-1 text-sm text-base-content/70">
                                            {{ format_date_range_compact($edition->starts_at, $edition->ends_at) }}
                                            @if ($edition->compactPlaceSummary() !== '')
                                                · {{ $edition->compactPlaceSummary() }}
                                            @endif
                                        </div>
                                    </div>
                                    @if ($edition->isCancelled())
                                        <x-badge
                                            :value="__('ui.events.cancelled_short')"
                                            icon="o-x-circle"
                                            class="badge-warning badge-sm shrink-0 font-semibold normal-case"
                                        />
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-tab>

                <x-tab name="hosts" :label="__('ui.event_series.tab_hosts')" class="p-4 sm:p-6" data-ui="event-series-tab-hosts" icon="o-users">
                    @if ($hosts === [])
                        <p class="text-base-content/70">{{ __('ui.event_series.empty_hosts') }}</p>
                    @else
                        <ul class="space-y-3" data-ui="event-series-hosts-list">
                            @foreach ($hosts as $host)
                                <li class="flex items-center gap-3" data-ui="event-series-host">
                                    @if ($host['type'] === 'organization' && $host['organization'] !== null)
                                        <livewire:activities.organization-badge-contact
                                            :organization="$host['organization']"
                                            :key="'series-host-org-'.$host['organization']->id"
                                        />
                                    @elseif ($host['user'] !== null)
                                        <livewire:activities.user-badge-contact
                                            :user="$host['user']"
                                            :key="'series-host-user-'.$host['user']->id"
                                        />
                                    @else
                                        <span>{{ $host['label'] }}</span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-tab>

                <x-tab name="activities" :label="__('ui.event_series.tab_activities')" class="p-4 sm:p-6" data-ui="event-series-tab-activities" icon="o-puzzle-piece">
                    @if ($activities->isEmpty())
                        <p class="text-base-content/70">{{ __('ui.event_series.empty_activities') }}</p>
                    @else
                        <ul class="divide-y divide-base-300" data-ui="event-series-activities-list">
                            @foreach ($activities as $activity)
                                <li class="flex flex-wrap items-center justify-between gap-3 py-3" data-ui="event-series-activity">
                                    <div class="min-w-0 flex-1">
                                        <a
                                            href="{{ route('activities.show', $activity) }}"
                                            wire:navigate
                                            class="link link-hover font-semibold break-words"
                                        >{{ $activity->name }}</a>
                                        @if ($activity->slot?->event)
                                            <div class="mt-1 text-sm text-base-content/70">
                                                <a
                                                    href="{{ route('events.show', $activity->slot->event) }}"
                                                    wire:navigate
                                                    class="link link-primary"
                                                >{{ $activity->slot->event->name }}</a>
                                            </div>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-tab>

                <x-tab name="stats" :label="__('ui.event_series.tab_stats')" class="p-4 sm:p-6" data-ui="event-series-tab-stats" icon="o-chart-bar">
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3" data-ui="event-series-stats">
                        <div class="ui-activity-show-info-panel ui-activity-show-stat-panel flex items-center rounded-2xl">
                            <x-stat
                                title="{{ __('ui.event_series.stats_editions') }}"
                                value="{{ $stats['editions_count'] }}"
                                icon="o-calendar-days"
                                class="ui-stat-embed ui-activity-show-stat"
                            />
                        </div>
                        <div class="ui-activity-show-info-panel ui-activity-show-stat-panel flex items-center rounded-2xl">
                            <x-stat
                                title="{{ __('ui.event_series.stats_upcoming') }}"
                                value="{{ $stats['upcoming_count'] }}"
                                icon="o-arrow-right"
                                class="ui-stat-embed ui-activity-show-stat"
                            />
                        </div>
                        <div class="ui-activity-show-info-panel ui-activity-show-stat-panel flex items-center rounded-2xl">
                            <x-stat
                                title="{{ __('ui.event_series.stats_past') }}"
                                value="{{ $stats['past_count'] }}"
                                icon="o-clock"
                                class="ui-stat-embed ui-activity-show-stat"
                            />
                        </div>
                        <div class="ui-activity-show-info-panel ui-activity-show-stat-panel flex items-center rounded-2xl">
                            <x-stat
                                title="{{ __('ui.event_series.stats_cancelled') }}"
                                value="{{ $stats['cancelled_count'] }}"
                                icon="o-x-circle"
                                class="ui-stat-embed ui-activity-show-stat"
                            />
                        </div>
                        <div class="ui-activity-show-info-panel ui-activity-show-stat-panel flex items-center rounded-2xl">
                            <x-stat
                                title="{{ __('ui.event_series.stats_activities') }}"
                                value="{{ $stats['activities_count'] }}"
                                icon="o-puzzle-piece"
                                class="ui-stat-embed ui-activity-show-stat"
                            />
                        </div>
                        <div class="ui-activity-show-info-panel ui-activity-show-stat-panel flex items-center rounded-2xl">
                            <x-stat
                                title="{{ __('ui.event_series.stats_participants') }}"
                                value="{{ $stats['participants_unique'] }}/{{ $stats['participants_total'] }}"
                                icon="o-users"
                                class="ui-stat-embed ui-activity-show-stat"
                            />
                        </div>
                    </div>
                    <p class="mt-3 text-sm text-base-content/60">{{ __('ui.event_series.stats_participants_hint') }}</p>
                </x-tab>
            </x-ui.tabs-with-toolbar>
        </div>
    </div>
</div>
