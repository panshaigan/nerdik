<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AvailabilityStatus;
use App\Models\User;
use App\Models\UserAvailabilityDateException;
use App\Models\UserAvailabilityWeekday;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class UserAvailabilityService
{
    /**
     * @param  array{mon: bool, tue: bool, wed: bool, thu: bool, fri: bool, sat: bool, sun: bool}  $weekdays
     */
    public function saveWeekdayDefaults(User $user, array $weekdays): UserAvailabilityWeekday
    {
        return UserAvailabilityWeekday::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'mon' => (bool) ($weekdays['mon'] ?? false),
                'tue' => (bool) ($weekdays['tue'] ?? false),
                'wed' => (bool) ($weekdays['wed'] ?? false),
                'thu' => (bool) ($weekdays['thu'] ?? false),
                'fri' => (bool) ($weekdays['fri'] ?? false),
                'sat' => (bool) ($weekdays['sat'] ?? false),
                'sun' => (bool) ($weekdays['sun'] ?? false),
            ],
        );
    }

    public function upsertDateException(User $user, CarbonInterface $date, AvailabilityStatus $status): UserAvailabilityDateException
    {
        $normalized = Carbon::parse($date)->startOfDay();

        return UserAvailabilityDateException::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'date' => $normalized->toDateString(),
            ],
            ['status' => $status],
        );
    }

    /**
     * Upsert the same status for every day from $from through $to (inclusive).
     *
     * @return list<UserAvailabilityDateException>
     */
    public function upsertDateExceptionRange(
        User $user,
        CarbonInterface $from,
        CarbonInterface $to,
        AvailabilityStatus $status,
    ): array {
        $start = Carbon::parse($from)->startOfDay();
        $end = Carbon::parse($to)->startOfDay();

        if ($end->lessThan($start)) {
            throw ValidationException::withMessages([
                'exceptionTo' => [__('ui.availability.invalid_range')],
            ]);
        }

        if ($start->diffInDays($end) > 366) {
            throw ValidationException::withMessages([
                'exceptionTo' => [__('ui.profile.availability.exception_range_too_long')],
            ]);
        }

        $rows = [];
        $cursor = $start->copy();
        while ($cursor->lessThanOrEqualTo($end)) {
            $rows[] = $this->upsertDateException($user, $cursor, $status);
            $cursor->addDay();
        }

        return $rows;
    }

    public function removeDateException(User $user, CarbonInterface $date): void
    {
        UserAvailabilityDateException::query()
            ->where('user_id', $user->id)
            ->whereDate('date', Carbon::parse($date)->toDateString())
            ->delete();
    }

    public function removeDateExceptionRange(User $user, CarbonInterface $from, CarbonInterface $to): void
    {
        $start = Carbon::parse($from)->toDateString();
        $end = Carbon::parse($to)->toDateString();

        UserAvailabilityDateException::query()
            ->where('user_id', $user->id)
            ->whereDate('date', '>=', $start)
            ->whereDate('date', '<=', $end)
            ->delete();
    }

    /**
     * @return Collection<int, UserAvailabilityDateException>
     */
    public function upcomingExceptions(User $user, int $limit = 366): Collection
    {
        return UserAvailabilityDateException::query()
            ->where('user_id', $user->id)
            ->whereDate('date', '>=', now()->toDateString())
            ->orderBy('date')
            ->limit($limit)
            ->get();
    }

    /**
     * Collapse consecutive same-status exception days into ranges for display.
     *
     * @param  Collection<int, UserAvailabilityDateException>  $exceptions
     * @return list<array{from: string, to: string, status: string, status_label: string}>
     */
    public function groupExceptionsIntoRanges(Collection $exceptions): array
    {
        $ranges = [];
        $current = null;

        foreach ($exceptions->sortBy(fn (UserAvailabilityDateException $row) => $row->date->toDateString())->values() as $row) {
            $date = $row->date->toDateString();
            $status = $row->status->value;

            if ($current === null) {
                $current = [
                    'from' => $date,
                    'to' => $date,
                    'status' => $status,
                    'status_label' => $row->status->label(),
                ];

                continue;
            }

            $nextExpected = Carbon::parse($current['to'])->addDay()->toDateString();
            if ($current['status'] === $status && $date === $nextExpected) {
                $current['to'] = $date;

                continue;
            }

            $ranges[] = $current;
            $current = [
                'from' => $date,
                'to' => $date,
                'status' => $status,
                'status_label' => $row->status->label(),
            ];
        }

        if ($current !== null) {
            $ranges[] = $current;
        }

        return $ranges;
    }

    public function statusFor(User $user, CarbonInterface $date): AvailabilityStatus
    {
        $day = Carbon::parse($date)->startOfDay();
        $dateString = $day->toDateString();

        $exception = UserAvailabilityDateException::query()
            ->where('user_id', $user->id)
            ->whereDate('date', $dateString)
            ->first();

        if ($exception !== null) {
            return $exception->status;
        }

        $weekdays = $user->relationLoaded('availabilityWeekday')
            ? $user->availabilityWeekday
            : $user->availabilityWeekday()->first();

        if ($weekdays === null) {
            return AvailabilityStatus::Uncertain;
        }

        $isoWeekday = (int) $day->isoWeekday();
        $defaultAvailable = $weekdays->isAvailableOnIsoWeekday($isoWeekday);

        if ($defaultAvailable === true) {
            return AvailabilityStatus::Available;
        }

        return AvailabilityStatus::Unavailable;
    }

    /**
     * @param  list<int>  $userIds
     * @return array<string, array<int, AvailabilityStatus>>
     */
    public function statusesForUsersBetween(array $userIds, CarbonInterface $start, CarbonInterface $end): array
    {
        if ($userIds === []) {
            return [];
        }

        $from = Carbon::parse($start)->startOfDay();
        $to = Carbon::parse($end)->startOfDay();

        if ($to->lessThan($from)) {
            throw ValidationException::withMessages([
                'range' => [__('ui.availability.invalid_range')],
            ]);
        }

        $users = User::query()
            ->whereIn('id', $userIds)
            ->with([
                'availabilityWeekday',
                'availabilityDateExceptions' => fn ($query) => $query
                    ->whereDate('date', '>=', $from->toDateString())
                    ->whereDate('date', '<=', $to->toDateString()),
            ])
            ->get()
            ->keyBy('id');

        $result = [];
        $cursor = $from->copy();

        while ($cursor->lessThanOrEqualTo($to)) {
            $dateKey = $cursor->toDateString();
            $result[$dateKey] = [];

            foreach ($userIds as $userId) {
                $user = $users->get($userId);
                if ($user === null) {
                    continue;
                }

                $result[$dateKey][$userId] = $this->statusForPreloaded($user, $cursor);
            }

            $cursor->addDay();
        }

        return $result;
    }

    public function statusForPreloaded(User $user, CarbonInterface $date): AvailabilityStatus
    {
        $day = Carbon::parse($date)->startOfDay();
        $dateString = $day->toDateString();

        if ($user->relationLoaded('availabilityDateExceptions')) {
            $exception = $user->availabilityDateExceptions->first(
                fn (UserAvailabilityDateException $row) => $row->date->toDateString() === $dateString,
            );
            if ($exception !== null) {
                return $exception->status;
            }
        } else {
            return $this->statusFor($user, $day);
        }

        $weekdays = $user->availabilityWeekday;
        if ($weekdays === null) {
            return AvailabilityStatus::Uncertain;
        }

        $isoWeekday = (int) $day->isoWeekday();
        if ($weekdays->isAvailableOnIsoWeekday($isoWeekday) === true) {
            return AvailabilityStatus::Available;
        }

        return AvailabilityStatus::Unavailable;
    }
}
