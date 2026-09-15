<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Jaybizzle\CrawlerDetect\CrawlerDetect;
use Symfony\Component\HttpFoundation\Response;

class BlockBotsFromAuth
{
    /**
     * Block known crawlers from auth and OAuth endpoints.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $detector = new CrawlerDetect;

        if ($detector->isCrawler($request->userAgent())) {
            abort(403);
        }

        return $next($request);
    }
}
