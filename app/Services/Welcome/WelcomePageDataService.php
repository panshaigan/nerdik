<?php

declare(strict_types=1);

namespace App\Services\Welcome;

use App\Models\User;
use App\Support\Ui\BrowseListingCardViewData;
use App\Support\Welcome\WelcomeHeroTagImage;
use App\Support\Welcome\WelcomeHeroTagImageResolver;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

final readonly class WelcomePageDataService
{
    private const STATS_CACHE_KEY = 'welcome.platform_stats';

    private const STATS_CACHE_TTL_SECONDS = 120;

    public function __construct(
        private WelcomeUpcomingQueryService $upcomingQuery,
        private WelcomePublicListingQuery $publicListingQuery,
        private WelcomeHeroTagImageResolver $heroTagImageResolver,
    ) {}

    /**
     * @return array{
     *     stats: WelcomePlatformStats,
     *     heroImage: WelcomeHeroTagImage|null,
     *     upcomingListings: Collection<int, BrowseListingCardViewData>
     * }
     */
    public function data(): array
    {
        return [
            'stats' => $this->platformStats(),
            'heroImage' => $this->heroTagImageResolver->resolve(),
            'upcomingListings' => $this->upcomingQuery->nearestPublicListings(6),
        ];
    }

    private function platformStats(): WelcomePlatformStats
    {
        /** @var WelcomePlatformStats $stats */
        $stats = Cache::remember(
            self::STATS_CACHE_KEY,
            self::STATS_CACHE_TTL_SECONDS,
            fn (): WelcomePlatformStats => WelcomePlatformStats::fromCounts([
                'users' => User::query()->where('is_deleted', false)->count(),
                'upcoming' => $this->publicListingQuery->upcomingCount(),
                'ongoing' => $this->publicListingQuery->ongoingCount(),
            ]),
        );

        return $stats;
    }
}
