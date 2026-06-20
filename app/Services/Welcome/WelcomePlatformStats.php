<?php

declare(strict_types=1);

namespace App\Services\Welcome;

final readonly class WelcomePlatformStats
{
    public function __construct(
        public int $usersCount,
        public int $upcomingListingsCount,
        public int $ongoingListingsCount,
    ) {}

    /**
     * @param  array{users: int, upcoming: int, ongoing: int}  $counts
     */
    public static function fromCounts(array $counts): self
    {
        return new self(
            usersCount: (int) $counts['users'],
            upcomingListingsCount: (int) $counts['upcoming'],
            ongoingListingsCount: (int) $counts['ongoing'],
        );
    }
}
