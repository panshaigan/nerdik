<?php

/**
 * Application color identity (DaisyUI semantic tokens).
 *
 * Default palette: playful community — violet primary, warm coral secondary,
 * mint accent, softly tinted bases. OKLCH throughout (DaisyUI 5).
 *
 * Override per environment with BRANDING_LIGHT_* / BRANDING_DARK_* env vars (see .env.example).
 *
 * Other considerations:
 * - Filament admin uses its own colors; unchanged here.
 * - Fonts: `resources/css/app.css` @theme.
 */
return [
    'light' => [
        'base-100' => env('BRANDING_LIGHT_BASE_100', 'oklch(99.2% 0.006 305)'),
        'base-200' => env('BRANDING_LIGHT_BASE_200', 'oklch(96.5% 0.012 305)'),
        'base-300' => env('BRANDING_LIGHT_BASE_300', 'oklch(92% 0.018 305)'),
        'base-content' => env('BRANDING_LIGHT_BASE_CONTENT', 'oklch(24% 0.045 305)'),
        'primary' => env('BRANDING_LIGHT_PRIMARY', 'oklch(52% 0.22 302)'),
        'primary-content' => env('BRANDING_LIGHT_PRIMARY_CONTENT', 'oklch(99% 0.01 302)'),
        'secondary' => env('BRANDING_LIGHT_SECONDARY', 'oklch(64% 0.17 22)'),
        'secondary-content' => env('BRANDING_LIGHT_SECONDARY_CONTENT', 'oklch(27% 0.09 22)'),
        'accent' => env('BRANDING_LIGHT_ACCENT', 'oklch(72% 0.12 178)'),
        'accent-content' => env('BRANDING_LIGHT_ACCENT_CONTENT', 'oklch(26% 0.06 178)'),
        'neutral' => env('BRANDING_LIGHT_NEUTRAL', 'oklch(28% 0.04 305)'),
        'neutral-content' => env('BRANDING_LIGHT_NEUTRAL_CONTENT', 'oklch(96% 0.01 305)'),
        'info' => env('BRANDING_LIGHT_INFO', 'oklch(58% 0.14 264)'),
        'info-content' => env('BRANDING_LIGHT_INFO_CONTENT', 'oklch(99% 0.02 264)'),
        'success' => env('BRANDING_LIGHT_SUCCESS', 'oklch(62% 0.15 155)'),
        'success-content' => env('BRANDING_LIGHT_SUCCESS_CONTENT', 'oklch(98% 0.02 155)'),
        'warning' => env('BRANDING_LIGHT_WARNING', 'oklch(84% 0.16 88)'),
        'warning-content' => env('BRANDING_LIGHT_WARNING_CONTENT', 'oklch(32% 0.08 55)'),
        'error' => env('BRANDING_LIGHT_ERROR', 'oklch(58% 0.2 25)'),
        'error-content' => env('BRANDING_LIGHT_ERROR_CONTENT', 'oklch(99% 0.02 25)'),
    ],

    'dark' => [
        'base-100' => env('BRANDING_DARK_BASE_100', 'oklch(24% 0.03 302)'),
        'base-200' => env('BRANDING_DARK_BASE_200', 'oklch(21% 0.035 302)'),
        'base-300' => env('BRANDING_DARK_BASE_300', 'oklch(18% 0.04 302)'),
        'base-content' => env('BRANDING_DARK_BASE_CONTENT', 'oklch(96% 0.02 305)'),
        'primary' => env('BRANDING_DARK_PRIMARY', 'oklch(74% 0.19 302)'),
        'primary-content' => env('BRANDING_DARK_PRIMARY_CONTENT', 'oklch(16% 0.06 302)'),
        'secondary' => env('BRANDING_DARK_SECONDARY', 'oklch(68% 0.15 25)'),
        'secondary-content' => env('BRANDING_DARK_SECONDARY_CONTENT', 'oklch(18% 0.07 25)'),
        'accent' => env('BRANDING_DARK_ACCENT', 'oklch(76% 0.13 178)'),
        'accent-content' => env('BRANDING_DARK_ACCENT_CONTENT', 'oklch(18% 0.05 178)'),
        'neutral' => env('BRANDING_DARK_NEUTRAL', 'oklch(32% 0.04 302)'),
        'neutral-content' => env('BRANDING_DARK_NEUTRAL_CONTENT', 'oklch(93% 0.02 305)'),
        'info' => env('BRANDING_DARK_INFO', 'oklch(68% 0.14 264)'),
        'info-content' => env('BRANDING_DARK_INFO_CONTENT', 'oklch(16% 0.05 264)'),
        'success' => env('BRANDING_DARK_SUCCESS', 'oklch(70% 0.13 155)'),
        'success-content' => env('BRANDING_DARK_SUCCESS_CONTENT', 'oklch(16% 0.05 155)'),
        'warning' => env('BRANDING_DARK_WARNING', 'oklch(80% 0.15 88)'),
        'warning-content' => env('BRANDING_DARK_WARNING_CONTENT', 'oklch(22% 0.06 55)'),
        'error' => env('BRANDING_DARK_ERROR', 'oklch(65% 0.18 22)'),
        'error-content' => env('BRANDING_DARK_ERROR_CONTENT', 'oklch(99% 0.02 22)'),
    ],
];
