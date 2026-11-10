@php
    use App\Support\Browse\BrowseSearchUrl;

    /** @var int $upcomingCreatedActivitiesCount */
    /** @var int $upcomingParticipatingActivitiesCount */
    /** @var int $upcomingInterestedActivitiesCount */
    /** @var int $userGroupsCount */
@endphp

<div class="grid grid-cols-2 gap-3 lg:grid-cols-4" data-ui="dashboard-activity-stats">
    <a
        href="{{ BrowseSearchUrl::myActivities() }}"
        wire:navigate
        class="ui-activity-show-info-panel ui-activity-show-stat-panel flex items-center rounded-2xl transition-opacity hover:opacity-90"
        data-ui="dashboard-stat-created-activities"
    >
        <x-stat
            :title="__('ui.dashboard.stats_created_activities')"
            :value="$upcomingCreatedActivitiesCount"
            icon="o-plus-circle"
            class="ui-stat-embed ui-activity-show-stat"
        />
    </a>

    <a
        href="#dashboard-feed"
        class="ui-activity-show-info-panel ui-activity-show-stat-panel flex items-center rounded-2xl transition-opacity hover:opacity-90"
        data-ui="dashboard-stat-participating-activities"
    >
        <x-stat
            :title="__('ui.dashboard.stats_participating_activities')"
            :value="$upcomingParticipatingActivitiesCount"
            icon="o-hand-raised"
            class="ui-stat-embed ui-activity-show-stat"
        />
    </a>

    <a
        href="#dashboard-feed"
        class="ui-activity-show-info-panel ui-activity-show-stat-panel flex items-center rounded-2xl transition-opacity hover:opacity-90"
        data-ui="dashboard-stat-interested-activities"
    >
        <x-stat
            :title="__('ui.dashboard.stats_interested_activities')"
            :value="$upcomingInterestedActivitiesCount"
            icon="o-heart"
            class="ui-stat-embed ui-activity-show-stat"
        />
    </a>

    <a
        href="{{ route('groups.index') }}"
        wire:navigate
        class="ui-activity-show-info-panel ui-activity-show-stat-panel flex items-center rounded-2xl transition-opacity hover:opacity-90"
        data-ui="dashboard-stat-my-groups"
    >
        <x-stat
            :title="__('ui.dashboard.stats_my_groups')"
            :value="$userGroupsCount"
            icon="o-user-group"
            class="ui-stat-embed ui-activity-show-stat"
        />
    </a>
</div>
