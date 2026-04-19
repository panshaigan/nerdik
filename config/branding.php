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
        'base-100' => 'oklch(98.5% 0.008 225)',     // Very light soft blue water
        'base-200' => 'oklch(94.5% 0.015 222)',
        'base-300' => 'oklch(88% 0.028 220)',       // Slightly deeper water feel
        'base-content' => 'oklch(21% 0.045 235)',   // Deep ocean text

        'primary' => 'oklch(66.3% 0.189 305)',      // Vibrant purple (unchanged)
        'primary-content' => 'oklch(98% 0.01 305)',

        'secondary' => 'oklch(82.3% 0.122 187.5)',  // Bright turquoise (unchanged)
        'secondary-content' => 'oklch(22% 0.04 190)',

        'accent' => 'oklch(70.5% 0.104 205.4)',     // Deep teal (unchanged)
        'accent-content' => 'oklch(98% 0.01 200)',

        'neutral' => 'oklch(48% 0.035 230)',        // Muted blue-gray
        'neutral-content' => 'oklch(96% 0.01 240)',

        'info' => 'oklch(75% 0.12 210)',
        'info-content' => 'oklch(20% 0.03 210)',

        'success' => 'oklch(78% 0.13 160)',
        'success-content' => 'oklch(20% 0.03 160)',

        'warning' => 'oklch(84% 0.16 88)',
        'warning-content' => 'oklch(32% 0.08 55)',

        'error' => 'oklch(65% 0.19 25)',
        'error-content' => 'oklch(98% 0.02 25)',
    ],

    'dark' => [
        'base-100' => 'oklch(16.5% 0.045 235)',    // Deep underwater blue
        'base-200' => 'oklch(13.5% 0.048 235)',
        'base-300' => 'oklch(11% 0.05 235)',       // Even deeper ocean
        'base-content' => 'oklch(94% 0.018 225)',  // Light glowing water text

        'primary' => 'oklch(74% 0.20 305)',        // Glowing purple
        'primary-content' => 'oklch(15% 0.03 305)',

        'secondary' => 'oklch(82.3% 0.122 187.5)', // Bright turquoise
        'secondary-content' => 'oklch(18% 0.04 190)',

        'accent' => 'oklch(65% 0.11 205)',
        'accent-content' => 'oklch(96% 0.01 200)',

        'neutral' => 'oklch(26% 0.04 235)',
        'neutral-content' => 'oklch(92% 0.015 230)',

        'info' => 'oklch(72% 0.13 210)',
        'info-content' => 'oklch(15% 0.03 210)',

        'success' => 'oklch(74% 0.14 160)',
        'success-content' => 'oklch(15% 0.03 160)',

        'warning' => 'oklch(82% 0.15 88)',
        'warning-content' => 'oklch(22% 0.06 55)',

        'error' => 'oklch(68% 0.18 22)',
        'error-content' => 'oklch(96% 0.02 22)',
    ],
];
