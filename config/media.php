<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Storage path prefix
    |--------------------------------------------------------------------------
    |
    | All Spatie media files are stored under this folder on the configured
    | disk (e.g. storage/app/public/media/{id}/).
    |
    */
    'storage_path_prefix' => env('MEDIA_STORAGE_PATH_PREFIX', 'media'),

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
    | Backfill conversions for library media attached before this pipeline existed:
    | php artisan media-library:regenerate --only-missing --with-responsive-images
    |
    */
    'queue_conversions' => env('MEDIA_QUEUE_CONVERSIONS', env('QUEUE_CONVERSIONS_BY_DEFAULT', true)),

    /*
    |--------------------------------------------------------------------------
    | Testing
    |--------------------------------------------------------------------------
    |
    | PHPUnit uses MEDIA_TEST_PROFILE=minimal by default. Set profile to "full"
    | in a single test to exercise avif/webp/jpeg + full responsive widths.
    |
    */
    'test_profile' => env('MEDIA_TEST_PROFILE', 'minimal'),

    'seed_bulk_tag_images_in_tests' => env('MEDIA_SEED_BULK_TAG_IMAGES_IN_TESTS', false),

    'testing' => [
        'conversion_formats' => ['webp'],
        'responsive_widths' => [128],
        'generate_responsive_images' => true,
    ],

    'full_test_formats' => ['avif', 'webp', 'jpeg'],

    /*
    |--------------------------------------------------------------------------
    | Avatar fixed conversions (square WebP derivatives)
    |--------------------------------------------------------------------------
    */
    'avatar_conversions' => [
        'avatar_32' => 32,
        'avatar_118' => 118,
        'avatar_512' => 512,
    ],

    /*
    |--------------------------------------------------------------------------
    | Picture presets (sizes hint + optional srcset width cap)
    |--------------------------------------------------------------------------
    */
    'presets' => [
        'tag_chip' => [
            'sizes' => '64px',
            'max_srcset_width' => 128,
        ],
        'tag_card' => [
            'sizes' => '(max-width: 640px) 100vw, 384px',
            'max_srcset_width' => 512,
        ],
        'tag_hero' => [
            'sizes' => '(max-width: 1024px) calc(100vw - 3rem), calc(min(80rem, 100vw) - 4rem)',
            'display_width' => 550,
            'max_srcset_width' => 1536,
        ],
        'listing_card' => [
            'sizes' => '(max-width: 767px) 100vw, (max-width: 1279px) 25vw, 286px',
            'display_width' => 286,
            'max_srcset_width' => 512,
        ],
        'listing_hero' => [
            'sizes' => '100vw',
            'max_srcset_width' => 1536,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | App shell full-page background (static public assets)
    |--------------------------------------------------------------------------
    |
    | Regenerate variants after changing originals:
    | php artisan app:generate-shell-backgrounds
    |
    */
    'app_shell_background' => [
        'sizes' => '100vw',
        'widths' => [384, 512, 640, 768, 1024, 1536, 1716],
        'desktop_min_width' => 1025,
        'mobile_widths' => [384, 512, 640, 768, 1024],
        'desktop_widths' => [1536, 1716],
        'mobile_max_width' => 640,
        'large_min_width' => 1536,
        'qualities' => [
            'mobile' => ['webp' => 82],
            'desktop' => ['webp' => 92],
            'large' => ['webp' => 98],
        ],
        'sources' => [
            'dark' => 'images/app/background-dark-original.png',
            'light' => 'images/app/background-light-original.png',
        ],
        'output_dir' => 'images/app/backgrounds',
    ],

    /*
    |--------------------------------------------------------------------------
    | Legacy sizes map (deprecated — use media.presets)
    |--------------------------------------------------------------------------
    */
    'sizes' => [
        'tag_chip' => '64px',
        'tag_card' => '(max-width: 640px) 100vw, 384px',
        'tag_hero' => '(max-width: 1024px) calc(100vw - 3rem), calc(min(80rem, 100vw) - 4rem)',
        'listing_card' => '(max-width: 767px) 100vw, (max-width: 1279px) 25vw, 286px',
    ],
];
