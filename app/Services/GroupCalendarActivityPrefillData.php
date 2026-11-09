<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Group;
use App\Models\User;
use Illuminate\Support\Collection;

final class GroupCalendarActivityPrefillData
{
    /**
     * @param  list<int>  $tagIds
     * @param  Collection<int, User>  $enrollableUsers
     */
    public function __construct(
        public readonly Group $group,
        public readonly string $date,
        public readonly string $selfHostedStartsAt,
        public readonly array $tagIds,
        public readonly Collection $enrollableUsers,
        public readonly int $playerCount,
    ) {}
}
