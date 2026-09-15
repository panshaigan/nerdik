<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pulse dashboard access that returns 404 for guests and non-admins.
 *
 * Replaces Laravel\Pulse\Http\Middleware\Authorize so denial does not
 * reveal the dashboard via 403.
 */
class AuthorizePulseDashboard
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Gate::denies('viewPulse')) {
            abort(404);
        }

        return $next($request);
    }
}
