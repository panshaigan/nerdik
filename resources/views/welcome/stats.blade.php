@php
    /** @var \App\Services\Welcome\WelcomePlatformStats $stats */
@endphp

<div class="mt-8 grid grid-cols-3 gap-3" data-ui="welcome-platform-stats">
    <div class="ui-activity-show-info-panel ui-activity-show-stat-panel flex items-center rounded-2xl">
        <x-stat
            :title="__('ui.welcome.stats_users')"
            :value="$stats->usersCount"
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
    <div class="ui-activity-show-info-panel ui-activity-show-stat-panel flex items-center rounded-2xl">
        <x-stat
            :title="__('ui.welcome.stats_ongoing')"
            :value="$stats->ongoingListingsCount"
            icon="o-play-circle"
            class="ui-stat-embed ui-activity-show-stat"
        />
    </div>
</div>
