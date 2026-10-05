<?php

declare(strict_types=1);

namespace App\Services\Welcome;

use App\Services\Platform\PlatformStats;
use App\Services\Platform\PlatformStatsService;
use App\Support\Ui\BrowseListingCardViewData;
use App\Support\Welcome\WelcomeHeroTagImage;
use App\Support\Welcome\WelcomeHeroTagImageResolver;
use Illuminate\Support\Collection;

final readonly class WelcomePageDataService
{
    public function __construct(
        private PlatformStatsService $platformStatsService,
        private WelcomeUpcomingQueryService $upcomingQuery,
        private WelcomeHeroTagImageResolver $heroTagImageResolver,
    ) {}

    /**
     * @return array{
     *     stats: PlatformStats,
     *     heroImage: WelcomeHeroTagImage|null,
     *     upcomingListings: Collection<int, BrowseListingCardViewData>
     * }
     */
    public function data(): array
    {
        return [
            'stats' => $this->platformStatsService->stats(),
            'heroImage' => $this->heroTagImageResolver->resolve(),
            'upcomingListings' => $this->upcomingQuery->nearestPublicListings(6),
        ];
    }
}
