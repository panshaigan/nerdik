<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Responsive image widths
    |--------------------------------------------------------------------------
    |
    | Pixel widths generated for each uploaded image when the source is wide
    | enough. Never upscaled beyond the original dimensions.
    |
    */
    'responsive_widths' => [128, 256, 384, 512, 768, 1024, 1536],

    'min_responsive_width' => 20,

    'conversion_qualities' => [
        'avif' => 50,
        'webp' => 85,
        'jpeg' => 85,
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue media conversions
    |--------------------------------------------------------------------------
    |
    | When false, conversions run synchronously (PHPUnit sets MEDIA_QUEUE_CONVERSIONS=false).
    |
    */
    'queue_conversions' => env('MEDIA_QUEUE_CONVERSIONS', env('QUEUE_CONVERSIONS_BY_DEFAULT', true)),

    'sizes' => [
        'tag_chip' => '64px',
        'tag_card' => '(max-width: 640px) 100vw, 384px',
        'tag_hero' => '(max-width: 1024px) 100vw, 640px',
        'listing_card' => '(max-width: 640px) 100vw, (max-width: 1280px) 50vw, 400px',
    ],
];
