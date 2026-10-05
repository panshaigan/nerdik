@php
    /** @var \App\Services\Platform\PlatformStats $stats */
    /** @var array{members: string, engaged_members: string, upcoming_events: string, upcoming_activities: string} $urls */
@endphp

<div class="grid grid-cols-2 gap-3 lg:grid-cols-4" data-ui="dashboard-admin-platform-stats">
    <a
        href="{{ $urls['members'] }}"
        class="ui-activity-show-info-panel ui-activity-show-stat-panel flex items-center rounded-2xl transition-opacity hover:opacity-90"
        data-ui="dashboard-admin-stat-members"
    >
        <x-stat
            :title="__('ui.platform_stats.members')"
            :value="$stats->membersCount"
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
