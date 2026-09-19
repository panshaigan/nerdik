<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Activity;
use App\Models\ActivityUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class ActivityPeopleStats
{
    /**
     * Signup seats plus distinct hosts who are not already on any roster in the set.
     *
     * A host who runs several activities is counted once. A host already signed up
     * on any of those activities is not added again.
     *
     * @param  Builder<Activity>|list<int>  $activityIds
     */
    public function totalIncludingHosts(Builder|array $activityIds, bool $excludeAbsent = false): int
    {
        if ($this->isEmpty($activityIds)) {
            return 0;
        }

        return $this->signupCount($activityIds, $excludeAbsent)
            + $this->uniqueHostsNotOnRosterCount($activityIds, $excludeAbsent);
    }

    /**
     * Distinct people: roster user ids union activity hosts.
     *
     * @param  Builder<Activity>|list<int>  $activityIds
     */
    public function uniqueIncludingHosts(Builder|array $activityIds, bool $excludeAbsent = false): int
    {
        if ($this->isEmpty($activityIds)) {
            return 0;
        }

        $rosterUsers = $this->signupQuery($activityIds, $excludeAbsent)->select('activity_user.user_id');

        $hostUsers = Activity::query()
            ->whereIn('activities.id', $activityIds)
            ->whereNotNull('activities.created_by')
            ->select('activities.created_by as user_id');

        return (int) DB::query()
            ->fromSub($rosterUsers->union($hostUsers), 'people')
            ->count();
    }

    /**
     * @param  Builder<Activity>|list<int>  $activityIds
     */
    public function signupCount(Builder|array $activityIds, bool $excludeAbsent = false): int
    {
        if ($this->isEmpty($activityIds)) {
            return 0;
        }

        return (int) $this->signupQuery($activityIds, $excludeAbsent)->count();
    }

    /**
     * @param  Builder<Activity>|list<int>  $activityIds
     * @return Builder<ActivityUser>
     */
    private function signupQuery(Builder|array $activityIds, bool $excludeAbsent): Builder
    {
        return ActivityUser::query()
            ->whereIn('activity_user.activity_id', $activityIds)
            ->whereNull('activity_user.deleted_at')
            ->when($excludeAbsent, fn (Builder $query) => $query->where('activity_user.is_absent', false));
    }

    /**
     * @param  Builder<Activity>|list<int>  $activityIds
     */
    private function uniqueHostsNotOnRosterCount(Builder|array $activityIds, bool $excludeAbsent): int
    {
        $rosterUserIds = $this->signupQuery($activityIds, $excludeAbsent)->select('activity_user.user_id');

        return (int) Activity::query()
            ->whereIn('activities.id', $activityIds)
            ->whereNotNull('activities.created_by')
            ->whereNotIn('activities.created_by', $rosterUserIds)
            ->distinct()
            ->count('activities.created_by');
    }

    /**
     * @param  Builder<Activity>|list<int>  $activityIds
     */
    private function isEmpty(Builder|array $activityIds): bool
    {
        return is_array($activityIds) && $activityIds === [];
    }
}
