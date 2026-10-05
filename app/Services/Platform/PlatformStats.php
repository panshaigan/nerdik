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
        public int $engagedMembersCount,
        public int $upcomingEventsCount,
        public int $upcomingActivitiesCount,
        public int $eventsCreatedThisMonth,
        public int $activitiesCreatedThisMonth,
        public int $upcomingListingsCount,
        public int $ongoingListingsCount,
        public array $memberSignupTrend,
    ) {}

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
}
