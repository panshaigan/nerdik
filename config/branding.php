<?php

/**
 * Application color identity (DaisyUI semantic tokens).
 *
 * Values must be valid CSS color values (OKLCH recommended; matches DaisyUI 5).
 * Override per environment with BRANDING_LIGHT_* / BRANDING_DARK_* env vars (see .env.example).
 *
 * Other considerations:
 * - Filament admin (`AdminPanelProvider`) uses Filament's own `->colors()` API; change that separately if admin should match.
 * - Fonts are set in `resources/css/app.css` under `@theme` (`--font-sans`), not here.
 * - `app.css` custom rules using `var(--color-primary)` will pick up these overrides automatically.
 */
return [
    'light' => [
        'base-100' => env('BRANDING_LIGHT_BASE_100', 'oklch(100% 0 0)'),
        'base-200' => env('BRANDING_LIGHT_BASE_200', 'oklch(98% 0 0)'),
        'base-300' => env('BRANDING_LIGHT_BASE_300', 'oklch(95% 0 0)'),
        'base-content' => env('BRANDING_LIGHT_BASE_CONTENT', 'oklch(21% .006 285.885)'),
        'primary' => env('BRANDING_LIGHT_PRIMARY', 'oklch(45% .24 277.023)'),
        'primary-content' => env('BRANDING_LIGHT_PRIMARY_CONTENT', 'oklch(93% .034 272.788)'),
        'secondary' => env('BRANDING_LIGHT_SECONDARY', 'oklch(65% .241 354.308)'),
        'secondary-content' => env('BRANDING_LIGHT_SECONDARY_CONTENT', 'oklch(94% .028 342.258)'),
        'accent' => env('BRANDING_LIGHT_ACCENT', 'oklch(77% .152 181.912)'),
        'accent-content' => env('BRANDING_LIGHT_ACCENT_CONTENT', 'oklch(38% .063 188.416)'),
        'neutral' => env('BRANDING_LIGHT_NEUTRAL', 'oklch(14% .005 285.823)'),
        'neutral-content' => env('BRANDING_LIGHT_NEUTRAL_CONTENT', 'oklch(92% .004 286.32)'),
        'info' => env('BRANDING_LIGHT_INFO', 'oklch(74% .16 232.661)'),
        'info-content' => env('BRANDING_LIGHT_INFO_CONTENT', 'oklch(29% .066 243.157)'),
        'success' => env('BRANDING_LIGHT_SUCCESS', 'oklch(76% .177 163.223)'),
        'success-content' => env('BRANDING_LIGHT_SUCCESS_CONTENT', 'oklch(37% .077 168.94)'),
        'warning' => env('BRANDING_LIGHT_WARNING', 'oklch(82% .189 84.429)'),
        'warning-content' => env('BRANDING_LIGHT_WARNING_CONTENT', 'oklch(41% .112 45.904)'),
        'error' => env('BRANDING_LIGHT_ERROR', 'oklch(71% .194 13.428)'),
        'error-content' => env('BRANDING_LIGHT_ERROR_CONTENT', 'oklch(27% .105 12.094)'),
    ],

    'dark' => [
        'base-100' => env('BRANDING_DARK_BASE_100', 'oklch(25.33% .016 252.42)'),
        'base-200' => env('BRANDING_DARK_BASE_200', 'oklch(23.26% .014 253.1)'),
        'base-300' => env('BRANDING_DARK_BASE_300', 'oklch(21.15% .012 254.09)'),
        'base-content' => env('BRANDING_DARK_BASE_CONTENT', 'oklch(97.807% .029 256.847)'),
        'primary' => env('BRANDING_DARK_PRIMARY', 'oklch(58% .233 277.117)'),
        'primary-content' => env('BRANDING_DARK_PRIMARY_CONTENT', 'oklch(96% .018 272.314)'),
        'secondary' => env('BRANDING_DARK_SECONDARY', 'oklch(65% .241 354.308)'),
        'secondary-content' => env('BRANDING_DARK_SECONDARY_CONTENT', 'oklch(94% .028 342.258)'),
        'accent' => env('BRANDING_DARK_ACCENT', 'oklch(77% .152 181.912)'),
        'accent-content' => env('BRANDING_DARK_ACCENT_CONTENT', 'oklch(38% .063 188.416)'),
        'neutral' => env('BRANDING_DARK_NEUTRAL', 'oklch(14% .005 285.823)'),
        'neutral-content' => env('BRANDING_DARK_NEUTRAL_CONTENT', 'oklch(92% .004 286.32)'),
        'info' => env('BRANDING_DARK_INFO', 'oklch(74% .16 232.661)'),
        'info-content' => env('BRANDING_DARK_INFO_CONTENT', 'oklch(29% .066 243.157)'),
        'success' => env('BRANDING_DARK_SUCCESS', 'oklch(76% .177 163.223)'),
        'success-content' => env('BRANDING_DARK_SUCCESS_CONTENT', 'oklch(37% .077 168.94)'),
        'warning' => env('BRANDING_DARK_WARNING', 'oklch(82% .189 84.429)'),
        'warning-content' => env('BRANDING_DARK_WARNING_CONTENT', 'oklch(41% .112 45.904)'),
        'error' => env('BRANDING_DARK_ERROR', 'oklch(71% .194 13.428)'),
        'error-content' => env('BRANDING_DARK_ERROR_CONTENT', 'oklch(27% .105 12.094)'),
    ],
];
