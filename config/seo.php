<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Sitemap cache TTL (seconds)
    |--------------------------------------------------------------------------
    |
    | How long the generated sitemap.xml body is cached. Invalidate naturally
    | on expiry so new public events/activities appear without manual flush.
    |
    */

    'sitemap_cache_seconds' => (int) env('SEO_SITEMAP_CACHE_SECONDS', 3600),

];
