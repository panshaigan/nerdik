<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Symfony\Component\HttpFoundation\Response;

final class ForceLivewireJson
{
    /**
     * Force Livewire requests to expect JSON responses.
     *
     * This makes Laravel return `401/419` instead of redirecting to login.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->headers->has('X-Livewire')) {
            return $next($request);
        }

        $request->headers->set('Accept', 'application/json');
        $request->headers->set('X-Requested-With', 'XMLHttpRequest');

        // Preserve the page the user was trying to reach so login can redirect back.
        if (! Auth::check()) {
            $intended = $request->headers->get('referer') ?: $request->fullUrl();
            Redirect::setIntendedUrl($intended);
        }

        return $next($request);
    }
}
