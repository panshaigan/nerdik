<?php

declare(strict_types=1);

namespace App\Services\Platform;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class PlatformOnlineMembersQuery
{
    public const CACHE_KEY = 'platform.online_members.v2';

    public const CACHE_TTL_SECONDS = 30;

    public const WINDOW_MINUTES = 5;

    public function count(?Carbon $now = null): int
    {
        if ($now instanceof Carbon) {
            return $this->compute($now);
        }

        return (int) Cache::remember(
            self::CACHE_KEY,
            self::CACHE_TTL_SECONDS,
            fn (): int => $this->compute(now()),
        );
    }

    private function compute(Carbon $now): int
    {
        $presenceSince = $now->copy()->subMinutes(self::WINDOW_MINUTES);
        $sessionCutoff = $presenceSince->getTimestamp();

        $presenceUsers = DB::table('user_presence')
            ->join('users', 'users.id', '=', 'user_presence.user_id')
            ->where('users.is_deleted', false)
            ->where('user_presence.last_seen_at', '>=', $presenceSince)
            ->pluck('users.id');

        $sessionUsers = DB::table('sessions')
            ->join('users', 'users.id', '=', 'sessions.user_id')
            ->where('users.is_deleted', false)
            ->where('sessions.last_activity', '>=', $sessionCutoff)
            ->pluck('users.id');

        return $presenceUsers
            ->merge($sessionUsers)
            ->unique()
            ->count();
    }
}
