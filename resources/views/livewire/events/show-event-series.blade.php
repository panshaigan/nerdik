<div class="relative" data-ui="event-series-show" data-show-event-series-id="{{ $series->id }}">
    <div class="relative z-0 space-y-4 sm:space-y-6">
        <x-page-header :title="$series->name">
            @if ($series->links->isNotEmpty())
                <x-slot:subtitle>
                    <livewire:entity-links.manage-entity-links
                        :linkable="$series"
                        :show-list="true"
                        :listen-for-open-add="false"
                        instance-suffix="subtitle"
                        appearance="subtitle"
                        data-ui="event-series-show-entity-links"
                        :key="'event-series-entity-links-subtitle-'.$series->id"
                    />
                </x-slot:subtitle>
            @endif
            @if ($hasUpcomingFollow)
                <x-slot:info>
                    <div class="flex items-end justify-end" data-ui="event-series-show-info">
                        <div class="ml-auto shrink-0 self-end" data-ui="event-series-show-follow">
                            <x-ui.follow-interest-button
                                :has-interest="$hasUpcomingInterest"
                                :count="$upcomingInterestedPeopleCount"
                                add-action="toggleUpcomingInterest"
                                remove-action="toggleUpcomingInterest"
                                data-ui-prefix="event-series-show"
                            />
                        </div>
                    </div>
                </x-slot:info>
            @endif
        </x-page-header>

        <div class="ui-content-card relative rounded-2xl mb-4 md:mb-6">
            <x-ui.tabs-with-toolbar
                wire:model.live.preserve-scroll="tab"
                label-div-class="flex gap-5 px-3 pt-1"
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
                                    <x-ui.overflow-menu-item
                                        icon="o-link"
                                        wire:click="openAddEntityLink"
                                        data-ui="event-series-show-add-link"
                                    >
                                        {{ __('ui.entity_links.add_action') }}
                                    </x-ui.overflow-menu-item>
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
                        @endauth
                    </div>
                </x-slot:toolbar>
                <x-tab name="events" :label="__('ui.event_series.tab_events')" class="p-4 sm:p-6" data-ui="event-series-tab-events" icon="o-calendar-days">
                    @if ($events->isEmpty())
                        <p class="text-base-content/70">{{ __('ui.event_series.empty_events') }}</p>
                    @else
                        <div
                            class="grid grid-cols-1 gap-5 lg:grid-cols-2 lg:gap-6"
                            data-ui="event-series-editions-list"
                        >
                            @foreach ($events as $edition)
                                @php
                                    $editionId = (int) $edition->id;
                                    $editionStats = $eventStatsById[$editionId] ?? [
                                        'confirmed_activities' => 0,
                                        'confirmed_participants' => 0,
                                        'available_places_label' => '∞',
                                        'interested_people_count' => 0,
                                    ];
                                    $editionMeta = $eventTileMetaById[$editionId] ?? [
                                        'time_summary' => '',
                                        'location_summary' => '',
                                        'location_places' => [],
                                        'details_url' => route('events.show', $edition),
                                        'edit_url' => route('events.edit', $edition),
                                        'can_edit' => false,
                                    ];
                                    $editionCoverPicture = $eventCoverPicturesById[$editionId] ?? null;
                                    $isInterestedInEdition = in_array($editionId, $interestedEventIds ?? [], true);
                                @endphp
                                <div
                                    wire:key="series-edition-{{ $edition->id }}"
                                    @class([
                                        'ui-tile-active status-dots group relative w-full overflow-visible rounded-xl border border-transparent',
                                        'status-dots-active ui-tile-pressable !border-primary/80 transition-all duration-200 hover:-translate-y-0.5 hover:border-primary hover:shadow-lg hover:shadow-primary/15 motion-reduce:hover:translate-y-0',
                                        'opacity-50' => $edition->isCancelled(),
                                    ])
                                    data-ui="event-series-edition"
                                >
                                    @if ($editionCoverPicture?->hasDisplayableImage())
                                        <div class="pointer-events-none absolute inset-0 z-0 overflow-hidden rounded-xl" aria-hidden="true">
                                            <div class="absolute inset-0 scale-105">
                                                <x-listing-card-picture
                                                    :picture="$editionCoverPicture"
                                                    class="h-full w-full object-cover"
                                                    loading="lazy"
                                                />
                                            </div>
                                            <div class="absolute inset-0 bg-base-100/85"></div>
                                            <div class="absolute inset-0 bg-gradient-to-t from-base-100/80 via-base-100/40 to-base-100/25"></div>
                                        </div>
                                    @endif

                                    <div class="status-dots-toolbar relative z-[3] flex items-center px-3 pt-2 sm:px-4">
                                        <div class="flex-1"></div>
                                        <div class="flex items-center justify-end gap-1 pointer-events-auto">
                                            <x-button
                                                :link="$editionMeta['details_url']"
                                                wire:navigate
                                                class="btn-ghost btn-square btn-sm text-base-content/80 hover:text-primary"
                                                :aria-label="__('ui.events.show_details').': '.$edition->name"
                                                :tooltip-bottom="__('ui.events.show_details')"
                                                icon="o-arrow-top-right-on-square"
                                                data-ui="event-card-open-details"
                                            />
                                            @auth
                                                @if ($editionMeta['can_edit'])
                                                    <x-button
                                                        :link="$editionMeta['edit_url']"
                                                        class="btn-ghost btn-square btn-sm text-base-content/80 hover:text-primary"
                                                        :aria-label="__('ui.events.edit_event').': '.$edition->name"
                                                        :tooltip-bottom="__('ui.events.edit_event')"
                                                        icon="o-pencil"
                                                        data-ui="event-card-edit"
                                                    />
                                                @endif
                                                    @if ($isInterestedInEdition)
                                                        <x-button
                                                            type="button"
                                                            wire:click="toggleEventInterest({{ $editionId }})"
                                                            class="btn btn-ghost btn-square btn-sm text-lg text-warning ui-action ui-action-interest-remove"
                                                            :tooltip-bottom="__('ui.interests.remove_from_interests')"
                                                            data-ui="event-card-interest-remove"
                                                            icon="s-star"
                                                        />
                                                    @else
                                                        <x-button
                                                            type="button"
                                                            wire:click="toggleEventInterest({{ $editionId }})"
                                                            class="btn-ghost btn-square btn-sm text-base-content/80 hover:text-warning ui-action ui-action-interest-add"
                                                            :tooltip-bottom="__('ui.interests.add_to_interests')"
                                                            data-ui="event-card-interest-add"
                                                            icon="o-star"
                                                        />
                                                    @endif
                                            @endauth
                                        </div>
                                    </div>

                                    <div class="relative px-3 pb-4 sm:px-4 sm:pb-5" data-ui="event-card">
                                        <button
                                            type="button"
                                            wire:click="openListingEventPreview({{ $editionId }})"
                                            wire:loading.attr="disabled"
                                            wire:target="openListingEventPreview({{ $editionId }})"
                                            wire:loading.class.delay="cursor-wait"
                                            class="absolute inset-0 z-[1] block cursor-pointer rounded-lg bg-primary/[0.02] ring-inset ring-primary/0 transition duration-200 motion-reduce:transition-none"
                                            aria-label="{{ $edition->name }}"
                                            data-ui="event-card-open-preview"
                                        ></button>
                                        <div
                                            wire:loading.delay
                                            wire:target="openListingEventPreview({{ $editionId }})"
                                            class="pointer-events-auto absolute inset-0 z-[15] flex items-center justify-center rounded-xl bg-base-100/60 backdrop-blur-[1px]"
                                            aria-live="polite"
                                            role="status"
                                            data-ui="event-card-preview-loading"
                                        >
                                            <span class="sr-only">{{ __('ui.common.loading') }}</span>
                                            <span class="loading loading-spinner loading-lg text-primary" aria-hidden="true"></span>
                                        </div>

                                        <div class="relative z-[2] pointer-events-none space-y-3">
                                            <div class="space-y-1.5">
                                                <h3 class="truncate text-lg font-semibold leading-snug text-base-content sm:text-xl">
                                                    {{ $edition->name }}
                                                </h3>
                                                @if ($edition->isCancelled())
                                                    <span class="badge badge-warning">{{ __('ui.events.cancelled_badge') }}</span>
                                                @endif
                                                @if ($editionMeta['time_summary'] !== '')
                                                    <p class="flex items-start gap-2 text-sm text-base-content/85">
                                                        <x-icon name="o-calendar" class="mt-0.5 h-4 w-4 shrink-0 text-base-content/50" />
                                                        <span>{{ $editionMeta['time_summary'] }}</span>
                                                    </p>
                                                @endif
                                                @if ($editionMeta['location_summary'] !== '' || $editionMeta['location_places'] !== [])
                                                    <div class="relative z-[3] flex items-start gap-2 pointer-events-auto text-sm text-base-content/85">
                                                        <x-icon name="o-map-pin" class="mt-0.5 h-4 w-4 shrink-0 text-base-content/50" />
                                                        <x-browse.place-search-links
                                                            :places="$editionMeta['location_places']"
                                                            :fallback="$editionMeta['location_summary']"
                                                        />
                                                    </div>
                                                @endif
                                            </div>

                                            <div
                                                class="grid grid-cols-3 gap-2"
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
                                                        title="{{ __('ui.events.interested_people_count') }}"
                                                        value="{{ $editionStats['interested_people_count'] }}"
                                                        icon="o-star"
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
                                            </div>
                                        </div>
                                    </div>
                                </div>
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

    @if ($canManageSeries ?? false)
        <livewire:entity-links.manage-entity-links
            :linkable="$series"
            :show-list="false"
            :listen-for-open-add="true"
            instance-suffix="shell"
            data-ui="event-series-show-entity-links-shell"
            :key="'event-series-entity-links-shell-'.$series->id"
        />
    @endif
</div>
