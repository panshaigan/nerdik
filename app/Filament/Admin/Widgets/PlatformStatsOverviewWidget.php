<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Resources\Activities\ActivityResource;
use App\Filament\Admin\Resources\Events\EventResource;
use App\Filament\Admin\Resources\Users\UserResource;
use App\Services\Platform\PlatformStats;
use App\Services\Platform\PlatformStatsService;
use Filament\Support\Enums\IconPosition;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PlatformStatsOverviewWidget extends StatsOverviewWidget
{
    protected static ?int $sort = -1;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $stats = app(PlatformStatsService::class)->stats();

        return [
            $this->membersStat($stats),
            $this->engagedMembersStat($stats),
            $this->upcomingEventsStat($stats),
            $this->upcomingActivitiesStat($stats),
        ];
    }

    private function membersStat(PlatformStats $stats): Stat
    {
        return Stat::make(__('ui.platform_stats.members'), $stats->membersCount)
            ->description(__('ui.platform_stats.trend_this_month', ['count' => $stats->membersAddedThisMonth]))
            ->descriptionIcon(Heroicon::OutlinedArrowTrendingUp, IconPosition::Before)
            ->chart($stats->memberSignupTrend)
            ->url(UserResource::getUrl('index'));
    }

    private function engagedMembersStat(PlatformStats $stats): Stat
    {
        return Stat::make(__('ui.platform_stats.engaged_members'), $stats->engagedMembersCount)
            ->description(__('ui.platform_stats.engaged_ratio', ['percent' => $stats->engagementRatioPercent()]))
            ->url(UserResource::getUrl('index'));
    }

    private function upcomingEventsStat(PlatformStats $stats): Stat
    {
        return Stat::make(__('ui.platform_stats.upcoming_events'), $stats->upcomingEventsCount)
            ->description(__('ui.platform_stats.created_this_month', ['count' => $stats->eventsCreatedThisMonth]))
            ->descriptionIcon(Heroicon::OutlinedArrowTrendingUp, IconPosition::Before)
            ->url(EventResource::getUrl('index'));
    }

    private function upcomingActivitiesStat(PlatformStats $stats): Stat
    {
        return Stat::make(__('ui.platform_stats.upcoming_activities'), $stats->upcomingActivitiesCount)
            ->description(__('ui.platform_stats.created_this_month', ['count' => $stats->activitiesCreatedThisMonth]))
            ->descriptionIcon(Heroicon::OutlinedArrowTrendingUp, IconPosition::Before)
            ->url(ActivityResource::getUrl('index'));
    }
}
