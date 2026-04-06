<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Date Format Fallback Locale
    |--------------------------------------------------------------------------
    |
    | Used when a locale-specific custom range template is not provided.
    |
    */
    'fallback_locale' => 'en',

    /*
    |--------------------------------------------------------------------------
    | Custom Date Range Templates
    |--------------------------------------------------------------------------
    |
    | Templates use tokens replaced in helpers.php:
    | :start_day, :end_day, :year, :start_year, :end_year,
    | :start_month_short, :end_month_short, :start_month_long, :end_month_long
    |
    */
    'ranges' => [
        'compact' => [
            'en' => [
                'same_day' => ':start_month_short :start_day, :year',
                'same_month' => ':start_month_short :start_day-:end_day, :year',
                'same_year' => ':start_month_short :start_day - :end_month_short :end_day, :year',
                'different_year' => ':start_month_short :start_day, :start_year - :end_month_short :end_day, :end_year',
            ],
            'pl' => [
                'same_day' => ':start_day :start_month_long_genitive :year',
                'same_month' => ':start_day-:end_day :start_month_long_genitive :year',
                'same_year' => ':start_day :start_month_long_genitive - :end_day :end_month_long_genitive :year',
                'different_year' => ':start_day :start_month_long_genitive :start_year - :end_day :end_month_long_genitive :end_year',
            ],
        ],
    ],
];
