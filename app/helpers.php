<?php

use App\Support\RichText;
use Carbon\Carbon;
use Illuminate\Support\HtmlString;

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

if (! function_exists('format_datetime_range_compact')) {
    /**
     * Format a datetime range in user's timezone while avoiding repeated parts:
     * - same day: "Mon D, YYYY · HH:mm - HH:mm"
     * - same month/year: "Mon D HH:mm → D HH:mm, YYYY"
     * - same year: "Mon D HH:mm → Mon D HH:mm, YYYY"
     * - different years: "Mon D, YYYY HH:mm → Mon D, YYYY HH:mm"
     *
     * @param  Carbon|DateTimeInterface|string|null  $start
     * @param  Carbon|DateTimeInterface|string|null  $end
     */
    function format_datetime_range_compact($start, $end): string
    {
        if ($start === null || $end === null) {
            return '';
        }

        $tz = auth()->check() && auth()->user()->timezone
            ? auth()->user()->timezone
            : config('app.timezone');

        $s = ($start instanceof Carbon ? $start->copy() : Carbon::parse($start))->setTimezone($tz);
        $e = ($end instanceof Carbon ? $end->copy() : Carbon::parse($end))->setTimezone($tz);

        if ($s->isSameDay($e)) {
            return $s->format('M j, Y').' · '.$s->format('H:i').' - '.$e->format('H:i');
        }

        if ($s->isSameYear($e) && $s->isSameMonth($e)) {
            return $s->format('M j H:i').' → '.$e->format('j H:i').', '.$s->format('Y');
        }

        if ($s->isSameYear($e)) {
            return $s->format('M j H:i').' → '.$e->format('M j H:i').', '.$s->format('Y');
        }

        return $s->format('M j, Y H:i').' → '.$e->format('M j, Y H:i');
    }
}

if (! function_exists('format_date_range_compact')) {
    /**
     * Format a date-only range in user's timezone while avoiding repeated parts:
     * - same day: "Mon D, YYYY"
     * - same month/year: "Mon D–D, YYYY"
     * - same year: "Mon D → Mon D, YYYY"
     * - different years: "Mon D, YYYY → Mon D, YYYY"
     *
     * @param  Carbon|DateTimeInterface|string|null  $start
     * @param  Carbon|DateTimeInterface|string|null  $end
     */
    function format_date_range_compact($start, $end): string
    {
        if ($start === null || $end === null) {
            return '';
        }

        $tz = auth()->check() && auth()->user()->timezone
            ? auth()->user()->timezone
            : config('app.timezone');

        $s = ($start instanceof Carbon ? $start->copy() : Carbon::parse($start))->setTimezone($tz);
        $e = ($end instanceof Carbon ? $end->copy() : Carbon::parse($end))->setTimezone($tz);

        if ($s->isSameDay($e)) {
            return $s->format('M j, Y');
        }

        if ($s->isSameYear($e) && $s->isSameMonth($e)) {
            return $s->format('M j').'–'.$e->format('j').', '.$s->format('Y');
        }

        if ($s->isSameYear($e)) {
            return $s->format('M j').' → '.$e->format('M j').', '.$s->format('Y');
        }

        return $s->format('M j, Y').' → '.$e->format('M j, Y');
    }
}

if (! function_exists('rich_text_sanitize')) {
    /**
     * Sanitize rich HTML (e.g. from TinyMCE) before saving to the database.
     */
    function rich_text_sanitize(?string $html): ?string
    {
        return RichText::sanitize($html);
    }
}

if (! function_exists('rich_text')) {
    /**
     * Purified HTML safe for {!! rich_text($model->desc) !!} in Blade.
     */
    function rich_text(?string $stored): HtmlString
    {
        return RichText::html($stored);
    }
}

if (! function_exists('rich_text_excerpt')) {
    /**
     * Plain excerpt for cards and previews (no HTML).
     */
    function rich_text_excerpt(?string $stored, int $limit = 120): string
    {
        return RichText::excerpt($stored, $limit);
    }
}
