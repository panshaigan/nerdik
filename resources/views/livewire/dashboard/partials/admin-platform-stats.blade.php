@php
    /** @var \App\Services\Platform\PlatformStats $stats */
    /** @var array{online_members: string, members: string, engaged_members: string, upcoming_events: string, upcoming_activities: string} $urls */
@endphp

<div class="grid grid-cols-2 gap-3 lg:grid-cols-5" data-ui="dashboard-admin-platform-stats" wire:poll.30s>
    <a
        href="{{ $urls['online_members'] }}"
        class="ui-activity-show-info-panel ui-activity-show-stat-panel flex items-center rounded-2xl transition-opacity hover:opacity-90"
        data-ui="dashboard-admin-stat-online-members"
    >
        <x-stat
            :title="__('ui.platform_stats.online_members')"
            :value="$stats->onlineMembersCount"
            description="{{ $stats->onlineMembersWindowDescription() }}"
            icon="o-signal"
            class="ui-stat-embed ui-activity-show-stat"
        />
    </a>

    <a
        href="{{ $urls['members'] }}"
        class="ui-activity-show-info-panel ui-activity-show-stat-panel flex items-center rounded-2xl transition-opacity hover:opacity-90"
        data-ui="dashboard-admin-stat-members"
    >
        <x-stat
            :title="__('ui.platform_stats.members')"
            :value="$stats->membersCount"
            description="{{ $stats->membersSignupWindowsDescription() }}"
            icon="o-users"
            class="ui-stat-embed ui-activity-show-stat"
        />
    </a>

    <a
        href="{{ $urls['engaged_members'] }}"
        class="ui-activity-show-info-panel ui-activity-show-stat-panel flex items-center rounded-2xl transition-opacity hover:opacity-90"
        data-ui="dashboard-admin-stat-engaged-members"
    >
        <x-stat
            :title="__('ui.platform_stats.engaged_members')"
            :value="$stats->engagedMembersCount"
            description="{{ __('ui.platform_stats.engaged_ratio', ['percent' => $stats->engagementRatioPercent()]) }}"
            icon="o-user-group"
            class="ui-stat-embed ui-activity-show-stat"
        />
    </a>

    <a
        href="{{ $urls['upcoming_events'] }}"
        class="ui-activity-show-info-panel ui-activity-show-stat-panel flex items-center rounded-2xl transition-opacity hover:opacity-90"
        data-ui="dashboard-admin-stat-upcoming-events"
    >
        <x-stat
            :title="__('ui.platform_stats.upcoming_events')"
            :value="$stats->upcomingEventsCount"
            icon="o-calendar-days"
            class="ui-stat-embed ui-activity-show-stat"
        />
    </a>

    <a
        href="{{ $urls['upcoming_activities'] }}"
        class="ui-activity-show-info-panel ui-activity-show-stat-panel flex items-center rounded-2xl transition-opacity hover:opacity-90"
        data-ui="dashboard-admin-stat-upcoming-activities"
    >
        <x-stat
            :title="__('ui.platform_stats.upcoming_activities')"
            :value="$stats->upcomingActivitiesCount"
            icon="o-sparkles"
            class="ui-stat-embed ui-activity-show-stat"
        />
    </a>
</div>
