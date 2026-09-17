<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Seo\SitemapBuilder;
use Symfony\Component\HttpFoundation\Response;

final class SitemapController extends Controller
{
    public function __invoke(SitemapBuilder $sitemap): Response
    {
        $maxAge = max(0, (int) config('seo.sitemap_cache_seconds', 3600));

        return response($sitemap->xml(), 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age='.$maxAge,
        ]);
    }
}
