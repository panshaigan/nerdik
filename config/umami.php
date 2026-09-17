<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Umami website ID
    |--------------------------------------------------------------------------
    |
    | From the Umami Cloud (or self-hosted) dashboard when you add a website.
    | Leave empty to disable the tracker script entirely.
    |
    */

    'website_id' => env('UMAMI_WEBSITE_ID'),

    /*
    |--------------------------------------------------------------------------
    | Tracker script URL
    |--------------------------------------------------------------------------
    |
    | Umami Cloud default. For self-hosting, point this at your instance's
    | script.js (and optionally set host_url if the collector differs).
    |
    */

    'script_url' => env('UMAMI_SCRIPT_URL', 'https://cloud.umami.is/script.js'),

    /*
    |--------------------------------------------------------------------------
    | Collector host URL (optional)
    |--------------------------------------------------------------------------
    |
    | Override where the tracker sends data. Usually unnecessary for Cloud.
    |
    */

    'host_url' => env('UMAMI_HOST_URL'),

    /*
    |--------------------------------------------------------------------------
    | Allowed domains (optional)
    |--------------------------------------------------------------------------
    |
    | Comma-separated hostnames. When set, the tracker only runs on matches
    | (useful so a production website ID never records localhost/staging).
    |
    */

    'domains' => env('UMAMI_DOMAINS'),

];
