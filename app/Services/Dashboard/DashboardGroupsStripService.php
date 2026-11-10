<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Models\Group;
use App\Models\User;
use App\Services\GroupAvailabilityService;
use App\Services\GroupPublicDirectoryService;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class DashboardGroupsStripService
{
    private const int MAX_GROUPS = 8;

    public function __construct(
        private readonly GroupAvailabilityService $availability,
        private readonly GroupPublicDirectoryService $directory,
    ) {}

    /**
     * @return array{
     *     groups: list<array{group: Group, users_count: int, next_free_day: ?CarbonInterface}>,
     *     suggested_group: ?Group,
     * }
     */
    public function forUser(User $user): array
    {
        $groups = Group::query()
            ->forUser($user)
            ->withCount('users')
            ->orderBy('name')
            ->limit(self::MAX_GROUPS)
            ->get();

        $cards = $groups->map(function (Group $group): array {
            return [
                'group' => $group,
                'users_count' => (int) $group->users_count,
                'next_free_day' => $this->nextSharedFreeDay($group),
            ];
        })->all();

        $suggested = null;
        if ($groups->isEmpty()) {
            $page = $this->directory->discover($user, perPage: 1);
            $suggested = $page->getCollection()->first();
        }

        return [
            'groups' => $cards,
            'suggested_group' => $suggested instanceof Group ? $suggested : null,
        ];
    }

    private function nextSharedFreeDay(Group $group): ?CarbonInterface
    {
        $timezone = display_timezone();
        $today = now()->setTimezone($timezone)->startOfDay();

        for ($monthOffset = 0; $monthOffset < 2; $monthOffset++) {
            $month = $today->copy()->addMonths($monthOffset);
            $summary = $this->availability->monthSummary($group, $month);
            $days = $summary['days'] ?? [];

            foreach ($days as $dateKey => $counts) {
                $day = Carbon::parse($dateKey, $timezone)->startOfDay();
                if ($day->lessThan($today)) {
                    continue;
                }

                $available = (int) ($counts['available'] ?? 0);
                $total = (int) ($counts['total'] ?? 0);
                if ($total < 1) {
                    continue;
                }

                $threshold = $total <= 2 ? $total : 2;
                if ($available >= $threshold) {
                    return $day;
                }
            }
        }

        return null;
    }
}
