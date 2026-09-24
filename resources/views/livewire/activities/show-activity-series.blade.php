<div class="relative" data-ui="activity-series-show" data-show-activity-series-id="{{ $series->id }}">
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
                        data-ui="activity-series-show-entity-links"
                        :key="'activity-series-entity-links-subtitle-'.$series->id"
                    />
                </x-slot:subtitle>
            @endif
            @if (filled($series->description) || $hasUpcomingFollow)
                <x-slot:info>
                    <div class="flex items-end gap-3" data-ui="activity-series-show-info">
                        @if (filled($series->description))
                            <p
                                class="min-w-0 flex-1 whitespace-pre-line text-sm text-base-content/80 sm:text-base"
                                data-ui="activity-series-show-description"
                            >{{ $series->description }}</p>
                        @endif
                        @if ($hasUpcomingFollow)
                            <div class="ml-auto shrink-0 self-end" data-ui="activity-series-show-follow">
                                <x-ui.follow-interest-button
                                    :has-interest="$hasUpcomingInterest"
                                    :count="$upcomingInterestedPeopleCount"
                                    add-action="toggleUpcomingInterest"
                                    remove-action="toggleUpcomingInterest"
                                    data-ui-prefix="activity-series-show"
                                />
                            </div>
                        @endif
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
                data-ui="activity-series-show-tabs"
            >
                <x-slot:toolbar>
                    <div class="flex shrink-0 items-center gap-1" data-ui="activity-series-show-tabs-toolbar">
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
                                    data-ui="activity-series-show-manage"
                                >
                                    <x-ui.overflow-menu-item
                                        icon="o-link"
                                        wire:click="openAddEntityLink"
                                        data-ui="activity-series-show-add-link"
                                    >
                                        {{ __('ui.entity_links.add_action') }}
                                    </x-ui.overflow-menu-item>
                                    <x-ui.overflow-menu-item
                                        icon="o-pencil"
                                        wire:click="openEditSeries"
                                        data-ui="activity-series-show-edit"
                                    >
                                        {{ __('ui.common.edit') }}
                                    </x-ui.overflow-menu-item>
                                    @if ($latestActivity)
                                        <x-ui.overflow-menu-item
                                            icon="o-plus"
                                            :href="route('activities.create', ['duplicate' => $latestActivity->slug])"
                                            data-ui="activity-series-show-create-activity"
                                        >
                                            {{ __('ui.activity_series.create_new_activity') }}
                                        </x-ui.overflow-menu-item>
                                    @endif
                                    <x-ui.overflow-menu-item
                                        icon="o-trash"
                                        class="text-error"
                                        wire:click="confirmDeleteSeries"
                                        data-ui="activity-series-show-delete"
                                    >
                                        {{ __('ui.common.delete') }}
                                    </x-ui.overflow-menu-item>
                                </x-ui.overflow-menu>
                            @endif
                        @endauth
                    </div>
                </x-slot:toolbar>

                <x-tab name="activities" :label="__('ui.activity_series.tab_activities')" class="p-4 sm:p-6" data-ui="activity-series-tab-activities" icon="o-puzzle-piece">
                    @if ($activities->isEmpty())
                        <p class="text-base-content/70">{{ __('ui.activity_series.empty_activities') }}</p>
                    @else
                        <div
                            class="grid grid-cols-1 gap-4 md:grid-cols-3 md:gap-6"
                            data-ui="activity-series-activities-list"
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

                <x-tab name="hosts" :label="__('ui.activity_series.tab_hosts')" class="p-4 sm:p-6" data-ui="activity-series-tab-hosts" icon="o-users">
                    @if ($hosts === [])
                        <p class="text-base-content/70">{{ __('ui.activity_series.empty_hosts') }}</p>
                    @else
                        <ul
                            class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4"
                            data-ui="activity-series-hosts-list"
                        >
                            @foreach ($hosts as $host)
                                <li class="min-w-0" data-ui="activity-series-host">
                                    <livewire:activities.user-badge-contact
                                        :user="$host['user']"
                                        :key="'activity-series-host-user-'.$host['user']->id"
                                    />
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-tab>

                <x-tab name="stats" :label="__('ui.activity_series.tab_stats')" class="p-4 sm:p-6" data-ui="activity-series-tab-stats" icon="o-chart-bar">
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3" data-ui="activity-series-stats">
                        <div class="ui-activity-show-info-panel ui-activity-show-stat-panel flex items-center rounded-2xl">
                            <x-stat
                                title="{{ __('ui.activity_series.stats_sessions') }}"
                                value="{{ $stats['sessions_count'] }}"
                                icon="o-puzzle-piece"
                                class="ui-stat-embed ui-activity-show-stat"
                            />
                        </div>
                        <div class="ui-activity-show-info-panel ui-activity-show-stat-panel flex items-center rounded-2xl">
                            <x-stat
                                title="{{ __('ui.activity_series.stats_upcoming') }}"
                                value="{{ $stats['upcoming_count'] }}"
                                icon="o-arrow-right"
                                class="ui-stat-embed ui-activity-show-stat"
                            />
                        </div>
                        <div class="ui-activity-show-info-panel ui-activity-show-stat-panel flex items-center rounded-2xl">
                            <x-stat
                                title="{{ __('ui.activity_series.stats_past') }}"
                                value="{{ $stats['past_count'] }}"
                                icon="o-clock"
                                class="ui-stat-embed ui-activity-show-stat"
                            />
                        </div>
                        <div class="ui-activity-show-info-panel ui-activity-show-stat-panel flex items-center rounded-2xl">
                            <x-stat
                                title="{{ __('ui.activity_series.stats_cancelled') }}"
                                value="{{ $stats['cancelled_count'] }}"
                                icon="o-x-circle"
                                class="ui-stat-embed ui-activity-show-stat"
                            />
                        </div>
                        <div class="ui-activity-show-info-panel ui-activity-show-stat-panel flex items-center rounded-2xl">
                            <div class="ui-stat-embed ui-activity-show-stat relative w-full px-5 py-4">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="text-sm text-base-content/70">{{ __('ui.activity_series.stats_participants') }}</div>
                                    <x-popover class="shrink-0 transition-none" position="top" offset="8">
                                        <x-slot:trigger>
                                            <x-icon
                                                name="o-information-circle"
                                                class="h-4 w-4 text-base-content/50"
                                            />
                                        </x-slot:trigger>
                                        <x-slot:content class="!w-72 max-w-[min(18rem,calc(100vw-2rem))] whitespace-normal text-sm text-base-content">
                                            {{ __('ui.activity_series.stats_participants_hint') }}
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
        @include('livewire.partials.edit-series-modal', [
            'title' => __('ui.activity_series.edit_title'),
            'nameLabel' => __('ui.activity_series.name'),
            'descriptionLabel' => __('ui.activity_series.description'),
        ])

        <livewire:entity-links.manage-entity-links
            :linkable="$series"
            :show-list="false"
            :listen-for-open-add="true"
            instance-suffix="shell"
            data-ui="activity-series-show-entity-links-shell"
            :key="'activity-series-entity-links-shell-'.$series->id"
        />
    @endif
</div>
