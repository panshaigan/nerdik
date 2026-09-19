<div class="relative" data-ui="event-series-show" data-show-event-series-id="{{ $series->id }}">
    <div class="relative z-0 space-y-4 sm:space-y-6">
        <x-page-header :title="$series->name">
            <x-slot:info>
                <div
                    class="flex flex-col gap-3 sm:flex-row sm:items-stretch"
                    data-ui="event-series-show-info"
                >
                        <x-stat
                            title="{{ __('ui.event_series.stats_editions') }}"
                            value="{{ $stats['editions_count'] }}"
                            icon="o-calendar-days"
                            class="ui-stat-embed ui-activity-show-stat"
                        />
                        <x-stat
                            title="{{ __('ui.event_series.stats_activities') }}"
                            value="{{ $stats['activities_count'] }}"
                            icon="o-puzzle-piece"
                            class="ui-stat-embed ui-activity-show-stat"
                        />
                        <div class="ui-stat-embed ui-activity-show-stat relative w-full px-5 py-4">
                            <div class="flex items-start justify-between gap-2">
                                <div class="text-sm text-base-content/70">{{ __('ui.event_series.stats_participants') }}</div>
                                <x-popover class="shrink-0 transition-none" position="top" offset="8">
                                    <x-slot:trigger>
                                        <x-icon
                                            name="o-information-circle"
                                            class="h-4 w-4 text-base-content/50"
                                        />
                                    </x-slot:trigger>
                                    <x-slot:content class="!w-72 max-w-[min(18rem,calc(100vw-2rem))] whitespace-normal text-sm text-base-content">
                                        {{ __('ui.event_series.stats_participants_hint') }}
                                    </x-slot:content>
                                </x-popover>
                            </div>
                            <div class="mt-1 flex items-center gap-2 text-2xl font-semibold">
                                <x-icon name="o-users" class="h-6 w-6 shrink-0 opacity-70" />
                                <span>{{ $stats['participants_unique'] }}/{{ $stats['participants_total'] }}</span>
                            </div>
                        </div>
                </div>
            </x-slot:info>
        </x-page-header>

        <div class="ui-content-card relative rounded-2xl mb-4 md:mb-6">
            <x-ui.tabs-with-toolbar
                wire:model.live.preserve-scroll="tab"
                label-div-class="flex gap-5 overflow-x-auto px-3 pt-1"
                label-class="tab tab-lifted tab-md !px-0 !py-2 pb-2 text-sm font-semibold text-base-content/70 hover:text-base-content"
                active-class="!text-base-content border-b border-primary text-primary"
                tabs-class="w-full"
                toolbar-wrapper-class="flex shrink-0 items-center gap-1 px-2 sm:px-3"
                data-ui="event-series-show-tabs"
            >
                <x-slot:toolbar>
                    <div class="flex shrink-0 items-center gap-1" data-ui="event-series-show-tabs-toolbar">
                        @if ($sharePayload)
                            <x-ui.share-menu :payload="$sharePayload" />
                        @endif
                        @if ($calendarPayload)
                            <x-ui.calendar-menu :payload="$calendarPayload" />
                        @endif
                        @auth
                            @if ($canManageSeries)
                                <x-ui.overflow-menu
                                    icon="o-cog-6-tooth"
                                    :label="__('ui.common.manage')"
                                    panel-class="w-64"
                                    data-ui="event-series-show-manage"
                                >
                                    @if ($latestEvent)
                                        <x-ui.overflow-menu-item
                                            icon="o-plus"
                                            :href="route('events.create', ['duplicate' => $latestEvent->slug])"
                                            data-ui="event-series-show-create-event"
                                        >
                                            {{ __('ui.event_series.create_new_event') }}
                                        </x-ui.overflow-menu-item>
                                    @endif
                                    <x-ui.overflow-menu-item
                                        icon="o-trash"
                                        class="text-error"
                                        wire:click="confirmDeleteSeries"
                                        data-ui="event-series-show-delete"
                                    >
                                        {{ __('ui.common.delete') }}
                                    </x-ui.overflow-menu-item>
                                </x-ui.overflow-menu>
                            @endif
                            @if ($hasUpcomingFollow)
                                @if ($hasUpcomingInterest)
                                    <x-button
                                        type="button"
                                        wire:click="toggleUpcomingInterest"
                                        class="btn-ghost btn-square btn-sm text-lg text-warning ui-action ui-action-interest-remove"
                                        :tooltip="__('ui.interests.remove_from_interests')"
                                        :aria-label="__('ui.interests.remove_from_interests')"
                                        data-ui="event-series-show-interest-remove"
                                        icon="s-star"
                                    />
                                @else
                                    <x-button
                                        type="button"
                                        wire:click="toggleUpcomingInterest"
                                        class="btn-ghost btn-square btn-sm text-base-content/80 hover:text-warning ui-action ui-action-interest-add"
                                        :tooltip="__('ui.interests.add_to_interests')"
                                        :aria-label="__('ui.interests.add_to_interests')"
                                        data-ui="event-series-show-interest-add"
                                        icon="o-star"
                                    />
                                @endif
                            @endif
                        @endauth
                    </div>
                </x-slot:toolbar>
                <x-tab name="events" :label="__('ui.event_series.tab_events')" class="p-4 sm:p-6" data-ui="event-series-tab-events" icon="o-calendar-days">
                    @if ($events->isEmpty())
                        <p class="text-base-content/70">{{ __('ui.event_series.empty_events') }}</p>
                    @else
                        <div class="space-y-2" data-ui="event-series-editions-list">
                            @foreach ($events as $edition)
                                @php
                                    $editionStats = $eventStatsById[(int) $edition->id] ?? [
                                        'confirmed_activities' => 0,
                                        'confirmed_participants' => 0,
                                        'available_places_label' => '∞',
                                        'interested_people_count' => 0,
                                    ];
                                @endphp
                                <div
                                    wire:key="series-edition-{{ $edition->id }}"
                                    @class([
                                        'grid grid-cols-1 gap-4 lg:grid-cols-4 lg:items-start',
                                        'opacity-50' => $edition->isCancelled(),
                                    ])
                                    data-ui="event-series-edition"
                                >
                                    <div class="min-w-0 lg:col-span-1">
                                        <x-cards.listing-card
                                            :listing="$edition"
                                            :interested-ids="$interestedEventIds"
                                            :return-url="$browsingReturnUrl"
                                        />
                                    </div>
                                    <div
                                        class="grid grid-cols-1 gap-2 self-start sm:grid-cols-3 lg:col-span-3 lg:grid-cols-3"
                                        data-ui="event-series-edition-stats"
                                    >
                                        <div class="ui-activity-show-info-panel ui-activity-show-stat-panel flex items-center rounded-2xl">
                                            <x-stat
                                                title="{{ __('ui.events.confirmed_activities') }}"
                                                value="{{ $editionStats['confirmed_activities'] }}"
                                                icon="o-puzzle-piece"
                                                class="ui-stat-embed ui-activity-show-stat"
                                            />
                                        </div>
                                        <div class="ui-activity-show-info-panel ui-activity-show-stat-panel flex items-center rounded-2xl">
                                            <x-stat
                                                title="{{ __('ui.events.confirmed_participants') }}"
                                                value="{{ $editionStats['confirmed_participants'] }}/{{ $editionStats['available_places_label'] }}"
                                                icon="o-users"
                                                class="ui-stat-embed ui-activity-show-stat"
                                            />
                                        </div>
                                        <div class="ui-activity-show-info-panel ui-activity-show-stat-panel flex items-center rounded-2xl">
                                            <x-stat
                                                title="{{ __('ui.events.interested_people_count') }}"
                                                value="{{ $editionStats['interested_people_count'] }}"
                                                icon="o-heart"
                                                class="ui-stat-embed ui-activity-show-stat"
                                            />
                                        </div>
                                    </div>
                                </div>
                                @if (! $loop->last)
                                    <x-ui.hr class="mt-6 mb-6" data-ui="event-series-edition-hr" icon="o-sparkles" color="neutral" />
                                @endif
                            @endforeach
                        </div>
                    @endif
                </x-tab>

                <x-tab name="hosts" :label="__('ui.event_series.tab_hosts')" class="p-4 sm:p-6" data-ui="event-series-tab-hosts" icon="o-users">
                    @if ($hosts === [])
                        <p class="text-base-content/70">{{ __('ui.event_series.empty_hosts') }}</p>
                    @else
                        <ul
                            class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4"
                            data-ui="event-series-hosts-list"
                        >
                            @foreach ($hosts as $host)
                                <li class="min-w-0" data-ui="event-series-host">
                                    <livewire:activities.user-badge-contact
                                        :user="$host['user']"
                                        :key="'series-host-user-'.$host['user']->id"
                                    />
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-tab>

                <x-tab name="activities" :label="__('ui.event_series.tab_activities')" class="p-4 sm:p-6" data-ui="event-series-tab-activities" icon="o-puzzle-piece">
                    @if ($activities->isEmpty())
                        <p class="text-base-content/70">{{ __('ui.event_series.empty_activities') }}</p>
                    @else
                        <div
                            class="grid grid-cols-1 gap-4 md:grid-cols-3 md:gap-6"
                            data-ui="event-series-activities-list"
                        >
                            @foreach ($activities as $activity)
                                <div wire:key="series-activity-{{ $activity->id }}" class="contents">
                                    <x-cards.listing-card
                                        :listing="$activity"
                                        :interested-ids="$interestedActivityIds"
                                        :return-url="$browsingReturnUrl"
                                    />
                                </div>
                            @endforeach
                        </div>
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
                            <div class="ui-stat-embed ui-activity-show-stat relative w-full px-5 py-4">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="text-sm text-base-content/70">{{ __('ui.event_series.stats_participants') }}</div>
                                    <x-popover class="shrink-0 transition-none" position="top" offset="8">
                                        <x-slot:trigger>
                                            <x-icon
                                                name="o-information-circle"
                                                class="h-4 w-4 text-base-content/50"
                                            />
                                        </x-slot:trigger>
                                        <x-slot:content class="!w-72 max-w-[min(18rem,calc(100vw-2rem))] whitespace-normal text-sm text-base-content">
                                            {{ __('ui.event_series.stats_participants_hint') }}
                                        </x-slot:content>
                                    </x-popover>
                                </div>
                                <div class="mt-1 flex items-center gap-2 text-2xl font-semibold">
                                    <x-icon name="o-users" class="h-6 w-6 shrink-0 opacity-70" />
                                    <span>{{ $stats['participants_unique'] }}/{{ $stats['participants_total'] }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </x-tab>
            </x-ui.tabs-with-toolbar>
        </div>
    </div>

    @include('livewire.partials.listing-preview-modals')

    <x-ui.confirm-modal
        wire:model="confirmModalOpen"
        :title="$confirmModalTitle"
        :message="$confirmModalMessage"
        confirm-action="runConfirmedAction"
    />
</div>
