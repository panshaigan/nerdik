<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AvailabilityStatus;
use App\Models\Group;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class GroupAvailabilityService
{
    public function __construct(
        private readonly UserAvailabilityService $availability,
    ) {}

    /**
     * @return array{
     *     days: array<string, array{available: int, unavailable: int, uncertain: int, total: int}>,
     *     member_ids: list<int>
     * }
     */
    public function monthSummary(Group $group, CarbonInterface $month, ?int $minAvailable = null, bool $requireAll = false): array
    {
        $start = Carbon::parse($month)->startOfMonth()->startOfDay();
        $end = Carbon::parse($month)->endOfMonth()->startOfDay();

        $members = $this->memberUsers($group);
        $memberIds = $members->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
        $total = count($memberIds);

        $statuses = $this->availability->statusesForUsersBetween($memberIds, $start, $end);

        $days = [];
        foreach ($statuses as $dateKey => $perUser) {
            $counts = $this->countStatuses($perUser);
            $counts['total'] = $total;

            $days[$dateKey] = $counts;
            $days[$dateKey]['matches_filter'] = $this->passesFilter($counts, $minAvailable, $requireAll, $total);
        }

        return [
            'days' => $days,
            'member_ids' => $memberIds,
        ];
    }

    /**
     * @return array{
     *     available: Collection<int, User>,
     *     unavailable: Collection<int, User>,
     *     uncertain: Collection<int, User>,
     *     counts: array{available: int, unavailable: int, uncertain: int, total: int}
     * }
     */
    public function dayDetail(Group $group, CarbonInterface $date): array
    {
        $members = $this->memberUsers($group);
        $memberIds = $members->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
        $day = Carbon::parse($date)->startOfDay();

        $users = User::query()
            ->whereIn('id', $memberIds)
            ->with([
                'availabilityWeekday',
                'availabilityDateExceptions' => fn ($query) => $query->whereDate('date', $day->toDateString()),
            ])
            ->get()
            ->keyBy('id');

        $available = collect();
        $unavailable = collect();
        $uncertain = collect();

        foreach ($memberIds as $userId) {
            $user = $users->get($userId);
            if ($user === null) {
                continue;
            }

            $status = $this->availability->statusForPreloaded($user, $day);

            match ($status) {
                AvailabilityStatus::Available => $available->push($user),
                AvailabilityStatus::Unavailable => $unavailable->push($user),
                AvailabilityStatus::Uncertain => $uncertain->push($user),
            };
        }

        $counts = [
            'available' => $available->count(),
            'unavailable' => $unavailable->count(),
            'uncertain' => $uncertain->count(),
            'total' => count($memberIds),
        ];

        return [
            'available' => $available,
            'unavailable' => $unavailable,
            'uncertain' => $uncertain,
            'counts' => $counts,
        ];
    }

    /**
     * @param  array<string, array{available: int, unavailable: int, uncertain: int, total: int, filtered_out?: bool}>  $days
     * @return list<string>
     */
    public function matchingDates(array $days, ?int $minAvailable, bool $requireAll): array
    {
        $matches = [];

        foreach ($days as $dateKey => $counts) {
            $total = (int) ($counts['total'] ?? 0);
            if ($this->passesFilter($counts, $minAvailable, $requireAll, $total)) {
                $matches[] = $dateKey;
            }
        }

        return $matches;
    }

    /**
     * @return Collection<int, User>
     */
    private function memberUsers(Group $group): Collection
    {
        return $group->users()
            ->where('users.is_deleted', false)
            ->orderBy('nickname')
            ->get();
    }

    /**
     * @param  array<int, AvailabilityStatus>  $perUser
     * @return array{available: int, unavailable: int, uncertain: int}
     */
    private function countStatuses(array $perUser): array
    {
        $available = 0;
        $unavailable = 0;
        $uncertain = 0;

        foreach ($perUser as $status) {
            match ($status) {
                AvailabilityStatus::Available => $available++,
                AvailabilityStatus::Unavailable => $unavailable++,
                AvailabilityStatus::Uncertain => $uncertain++,
            };
        }

        return [
            'available' => $available,
            'unavailable' => $unavailable,
            'uncertain' => $uncertain,
        ];
    }

    /**
     * @param  array{available: int, unavailable: int, uncertain: int, total?: int}  $counts
     */
    private function passesFilter(array $counts, ?int $minAvailable, bool $requireAll, int $total): bool
    {
        if ($requireAll && $total > 0) {
            return (int) $counts['available'] === $total;
        }

        if ($minAvailable !== null && $minAvailable > 0) {
            return (int) $counts['available'] >= $minAvailable;
        }

        return true;
    }
}
