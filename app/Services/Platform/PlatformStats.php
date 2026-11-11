<?php

declare(strict_types=1);

namespace App\Services\Platform;

final readonly class PlatformStats
{
    /**
     * @param  list<int>  $memberSignupTrend
     */
    public function __construct(
        public int $membersCount,
        public int $membersAddedThisMonth,
        public int $membersAddedThisWeek,
        public int $membersAddedLast7Days,
        public int $membersAddedLast3Days,
        public int $engagedMembersCount,
        public int $upcomingEventsCount,
        public int $upcomingActivitiesCount,
        public int $eventsCreatedThisMonth,
        public int $activitiesCreatedThisMonth,
        public int $upcomingListingsCount,
        public int $ongoingListingsCount,
        public array $memberSignupTrend,
        public int $onlineMembersCount = 0,
    ) {}

    public function withOnlineMembersCount(int $onlineMembersCount): self
    {
        return new self(
            membersCount: $this->membersCount,
            membersAddedThisMonth: $this->membersAddedThisMonth,
            membersAddedThisWeek: $this->membersAddedThisWeek,
            membersAddedLast7Days: $this->membersAddedLast7Days,
            membersAddedLast3Days: $this->membersAddedLast3Days,
            engagedMembersCount: $this->engagedMembersCount,
            upcomingEventsCount: $this->upcomingEventsCount,
            upcomingActivitiesCount: $this->upcomingActivitiesCount,
            eventsCreatedThisMonth: $this->eventsCreatedThisMonth,
            activitiesCreatedThisMonth: $this->activitiesCreatedThisMonth,
            upcomingListingsCount: $this->upcomingListingsCount,
            ongoingListingsCount: $this->ongoingListingsCount,
            memberSignupTrend: $this->memberSignupTrend,
            onlineMembersCount: $onlineMembersCount,
        );
    }

    public function onlineMembersWindowDescription(): string
    {
        return __('ui.platform_stats.online_window', [
            'minutes' => PlatformOnlineMembersQuery::WINDOW_MINUTES,
        ]);
    }

    public function usersCount(): int
    {
        return $this->membersCount;
    }

    public function engagementRatioPercent(): int
    {
        if ($this->membersCount === 0) {
            return 0;
        }

        return (int) round(($this->engagedMembersCount / $this->membersCount) * 100);
    }

    public function membersSignupWindowsDescription(): string
    {
        return implode(' · ', [
            __('ui.platform_stats.trend_this_month', ['count' => $this->membersAddedThisMonth]),
            __('ui.platform_stats.trend_last_7_days', ['count' => $this->membersAddedLast7Days]),
            __('ui.platform_stats.trend_last_3_days', ['count' => $this->membersAddedLast3Days]),
        ]);
    }
}
