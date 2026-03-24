<?php

use Carbon\Carbon;

if (! function_exists('parse_datetime_to_utc')) {
    /**
     * Parse a datetime string (e.g. from datetime-local input) as the current user's timezone and return Carbon in UTC.
     * Use when saving to DB so all datetimes are stored in UTC.
     */
    function parse_datetime_to_utc(?string $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }
        $tz = auth()->check() && auth()->user()->timezone
            ? auth()->user()->timezone
            : config('app.timezone');

        return Carbon::parse($value, $tz)->setTimezone('UTC');
    }
}

if (! function_exists('format_in_user_tz')) {
    /**
     * Format a date/time in the current user's timezone (or app timezone for guests).
     * All datetimes are stored in UTC in the database.
     *
     * @param  Carbon|DateTimeInterface|string|null  $date
     */
    function format_in_user_tz($date, string $format = 'Y-m-d H:i'): string
    {
        if ($date === null) {
            return '';
        }
        $carbon = $date instanceof Carbon ? $date->copy() : Carbon::parse($date);
        $tz = auth()->check() && auth()->user()->timezone
            ? auth()->user()->timezone
            : config('app.timezone');

        return $carbon->setTimezone($tz)->format($format);
    }
}
