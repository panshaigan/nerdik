@php
    /** @var \App\Services\Platform\PlatformStats $stats */
@endphp

<div class="my-12 grid grid-cols-2 gap-3 md:grid-cols-2" data-ui="welcome-platform-stats">
    <div class="ui-activity-show-info-panel ui-activity-show-stat-panel flex items-center rounded-2xl">
        <x-stat
            :title="__('ui.welcome.stats_users')"
            :value="$stats->membersCount"
            icon="o-users"
            class="ui-stat-embed ui-activity-show-stat"
        />
    </div>
    <div class="ui-activity-show-info-panel ui-activity-show-stat-panel flex items-center rounded-2xl">
        <x-stat
            :title="__('ui.welcome.stats_upcoming')"
            :value="$stats->upcomingListingsCount"
            icon="o-calendar-days"
            class="ui-stat-embed ui-activity-show-stat"
        />
    </div>
</div>
