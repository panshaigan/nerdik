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

    /*
    |--------------------------------------------------------------------------
    | Conversion qualities (1–100)
    |--------------------------------------------------------------------------
    |
    | Listing cards often include text in the artwork. Keep these high enough
    | that small type stays readable after responsive downscales. AVIF at ~50
    | is especially soft on letterforms — prefer mid-70s+.
    |
    | After changing these values, re-encode existing derivatives:
    | php artisan media:backfill-thumbnails --reencode
    |
    */
    'conversion_qualities' => [
        'avif' => 75,
        'webp' => 92,
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue media conversions
    |--------------------------------------------------------------------------
    |
    | When false, conversions run synchronously (PHPUnit sets MEDIA_QUEUE_CONVERSIONS=false).
    |
    | Backfill conversions for library media attached before this pipeline existed:
    | php artisan media:backfill-thumbnails
    | # or: php artisan media-library:regenerate --only-missing --with-responsive-images --force
    |
    | Production (VPS):
    | ./scripts/compose-exec.sh prod exec app php artisan media:backfill-thumbnails
    | # after quality config changes:
    | ./scripts/compose-exec.sh prod exec app php artisan media:backfill-thumbnails --reencode
    |
    */
    'queue_conversions' => env('MEDIA_QUEUE_CONVERSIONS', env('QUEUE_CONVERSIONS_BY_DEFAULT', true)),

    /*
    |--------------------------------------------------------------------------
    | Testing
    |--------------------------------------------------------------------------
    |
    | PHPUnit uses MEDIA_TEST_PROFILE=minimal by default. Set profile to "full"
    | in a single test to exercise avif/webp + full responsive widths.
    |
    */
    'test_profile' => env('MEDIA_TEST_PROFILE', 'minimal'),

    'seed_bulk_tag_images_in_tests' => env('MEDIA_SEED_BULK_TAG_IMAGES_IN_TESTS', false),

    'testing' => [
        'conversion_formats' => ['webp'],
        'responsive_widths' => [128],
        'generate_responsive_images' => true,
    ],

    'full_test_formats' => ['avif', 'webp'],

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
            'max_srcset_width' => 768,
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
    | Brand logo (static public assets)
    |--------------------------------------------------------------------------
    |
    | Regenerate variants after changing the source WebP:
    | php artisan app:generate-brand-logo
    |
    */
    'brand_logo' => [
        'source' => 'resources/brand/nerdik_brand_logo.webp',
        'output_dir' => 'images/app/brand',
        'widths' => [40, 48, 64, 80, 96, 128, 160, 192],
        'quality' => 90,
        'presets' => [
            'nav' => [
                'display_width' => 39,
                'variant_width' => 40,
                'retina_variant_width' => 80,
            ],
            'sm' => [
                'display_width' => 43,
                'variant_width' => 48,
                'retina_variant_width' => 96,
            ],
            'md' => [
                'display_width' => 86,
                'variant_width' => 96,
                'retina_variant_width' => 160,
            ],
            'admin' => [
                'display_width' => 34,
                'variant_width' => 40,
                'retina_variant_width' => 64,
            ],
        ],
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
