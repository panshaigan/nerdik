<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Platform\PlatformOnlineMembersQuery;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

final class TouchMemberPresence
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        $user = $request->user();

        if (! $user instanceof User || $user->is_deleted) {
            return;
        }

        if ($response->getStatusCode() >= 500) {
            return;
        }

        $throttleKey = 'member-presence:touch:'.$user->id;

        if (! Cache::add($throttleKey, true, 30)) {
            return;
        }

        DB::table('user_presence')->upsert(
            [
                'user_id' => $user->id,
                'last_seen_at' => now(),
            ],
            ['user_id'],
            ['last_seen_at'],
        );

        Cache::forget(PlatformOnlineMembersQuery::CACHE_KEY);
    }
}
