<?php

declare(strict_types=1);

namespace App\Services\Platform;

use App\Models\User;
use App\Services\Welcome\WelcomePublicListingQuery;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

final class PlatformStatsService
{
    public const CACHE_KEY = 'platform.stats.v3';

    public const CACHE_TTL_SECONDS = 600;

    public function __construct(
        private PlatformEngagementQuery $engagementQuery,
        private PlatformListingCountsQuery $listingCountsQuery,
        private WelcomePublicListingQuery $publicListingQuery,
        private PlatformOnlineMembersQuery $onlineMembersQuery,
    ) {}

    public function stats(?Carbon $now = null, bool $withOnlineMembers = false): PlatformStats
    {
        $resolvedNow = $now ?? now();

        /** @var PlatformStats $stats */
        $stats = Cache::remember(
            self::CACHE_KEY,
            self::CACHE_TTL_SECONDS,
            fn (): PlatformStats => $this->compute($resolvedNow),
        );

        if (! $withOnlineMembers) {
            return $stats;
        }

        return $stats->withOnlineMembersCount($this->onlineMembersQuery->count($now));
    }

    private function compute(Carbon $now): PlatformStats
    {
        $membersCount = User::query()->where('is_deleted', false)->count();

        return new PlatformStats(
            membersCount: $membersCount,
            membersAddedThisMonth: $this->membersAddedSince($now, $now->copy()->startOfMonth()),
            membersAddedThisWeek: $this->membersAddedSince($now, $now->copy()->startOfWeek()),
            membersAddedLast7Days: $this->membersAddedSince($now, $now->copy()->subDays(7)),
            membersAddedLast3Days: $this->membersAddedSince($now, $now->copy()->subDays(3)),
            engagedMembersCount: $this->engagementQuery->engagedMembersCount(),
            upcomingEventsCount: $this->listingCountsQuery->upcomingEventsCount($now),
            upcomingActivitiesCount: $this->listingCountsQuery->upcomingActivitiesCount($now),
            eventsCreatedThisMonth: $this->listingCountsQuery->eventsCreatedThisMonth($now),
            activitiesCreatedThisMonth: $this->listingCountsQuery->activitiesCreatedThisMonth($now),
            upcomingListingsCount: $this->publicListingQuery->upcomingCount(),
            ongoingListingsCount: $this->publicListingQuery->ongoingCount(),
            memberSignupTrend: $this->memberSignupTrend($now),
        );
    }

    private function membersAddedSince(Carbon $now, Carbon $since): int
    {
        return User::query()
            ->where('is_deleted', false)
            ->where('created_at', '>=', $since)
            ->count();
    }

    /**
     * @return list<int>
     */
    private function memberSignupTrend(Carbon $now): array
    {
        $trend = [];

        for ($daysAgo = 6; $daysAgo >= 0; $daysAgo--) {
            $date = $now->copy()->subDays($daysAgo)->toDateString();

            $trend[] = User::query()
                ->where('is_deleted', false)
                ->whereDate('created_at', $date)
                ->count();
        }

        return $trend;
    }
}
