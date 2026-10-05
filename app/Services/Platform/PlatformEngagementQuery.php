<?php

declare(strict_types=1);

namespace App\Services\Platform;

use Illuminate\Support\Facades\DB;

final class PlatformEngagementQuery
{
    public function engagedMembersCount(): int
    {
        $participants = DB::table('activity_user')
            ->join('users', 'users.id', '=', 'activity_user.user_id')
            ->where('users.is_deleted', false)
            ->where('activity_user.is_absent', false)
            ->whereNull('activity_user.deleted_at')
            ->select('users.id');

        $hosts = DB::table('activities')
            ->join('users', 'users.id', '=', 'activities.created_by')
            ->where('users.is_deleted', false)
            ->whereNull('activities.deleted_at')
            ->whereNotNull('activities.created_by')
            ->select('users.id');

        return (int) DB::query()
            ->fromSub($participants->union($hosts), 'engaged')
            ->distinct()
            ->count('id');
    }
}
