<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class StoreBrowsingReturnUrl
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->isMethod('GET') && ! $request->expectsJson()) {
            remember_browsing_return_url($request->getRequestUri());
        }

        return $response;
    }
}
