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

        $carbon = $carbon->setTimezone($tz)->locale(app()->getLocale());

        // Support translated month/day names when format uses textual tokens.
        // Falls back to PHP date formatting for numeric-only formats.
        return strpbrk($format, 'DFlM') !== false
            ? $carbon->translatedFormat($format)
            : $carbon->format($format);
    }
}

if (! function_exists('format_datetime_in_user_tz')) {
    /**
     * Locale-aware datetime formatting via Carbon isoFormat tokens.
     *
     * @param  Carbon|DateTimeInterface|string|null  $date
     */
    function format_datetime_in_user_tz($date, string $isoFormat = 'lll'): string
    {
        if ($date === null) {
            return '';
        }

        $carbon = $date instanceof Carbon ? $date->copy() : Carbon::parse($date);
        $tz = auth()->check() && auth()->user()->timezone
            ? auth()->user()->timezone
            : config('app.timezone');

        return $carbon->setTimezone($tz)->locale(app()->getLocale())->isoFormat($isoFormat);
    }
}

if (! function_exists('format_date_in_user_tz')) {
    /**
     * Locale-aware date-only formatting (keeps locale-specific day/month order).
     *
     * @param  Carbon|DateTimeInterface|string|null  $date
     */
    function format_date_in_user_tz($date, ?int $dateType = null): string
    {
        if ($date === null) {
            return '';
        }

        $carbon = $date instanceof Carbon ? $date->copy() : Carbon::parse($date);
        $tz = auth()->check() && auth()->user()->timezone
            ? auth()->user()->timezone
            : config('app.timezone');

        $carbon = $carbon->setTimezone($tz)->locale(app()->getLocale());
        if (! class_exists(\IntlDateFormatter::class)) {
            return $carbon->isoFormat('ll');
        }

        $resolvedDateType = $dateType ?? \IntlDateFormatter::MEDIUM;
        $formatter = new \IntlDateFormatter(
            app()->getLocale(),
            $resolvedDateType,
            \IntlDateFormatter::NONE,
            $tz
        );

        $formatted = $formatter->format($carbon);

        return $formatted === false ? $carbon->isoFormat('ll') : $formatted;
    }
}

if (! function_exists('date_range_template')) {
    /**
     * Return a locale-specific date range template with fallback to English/default.
     */
    function date_range_template(string $style, string $variant): string
    {
        $locale = app()->getLocale();
        $fallbackLocale = (string) config('date_formats.fallback_locale', config('app.fallback_locale', 'en'));

        $custom = config("date_formats.ranges.{$style}.{$locale}.{$variant}");
        if (is_string($custom) && $custom !== '') {
            return $custom;
        }

        $fallback = config("date_formats.ranges.{$style}.{$fallbackLocale}.{$variant}");
        if (is_string($fallback) && $fallback !== '') {
            return $fallback;
        }

        $defaults = [
            'same_day' => ':start_month_short :start_day, :year',
            'same_month' => ':start_month_short :start_day-:end_day, :year',
            'same_year' => ':start_month_short :start_day - :end_month_short :end_day, :year',
            'different_year' => ':start_month_short :start_day, :start_year - :end_month_short :end_day, :end_year',
        ];

        return $defaults[$variant] ?? ':start_month_short :start_day, :start_year - :end_month_short :end_day, :end_year';
    }
}

if (! function_exists('format_date_range_localized')) {
    /**
     * Reusable locale-aware date range formatter with configurable templates.
     *
     * @param  Carbon|DateTimeInterface|string|null  $start
     * @param  Carbon|DateTimeInterface|string|null  $end
     */
    function format_date_range_localized($start, $end, string $style = 'compact'): string
    {
        if ($start === null || $end === null) {
            return '';
        }

        $tz = auth()->check() && auth()->user()->timezone
            ? auth()->user()->timezone
            : config('app.timezone');
        $locale = app()->getLocale();

        $s = ($start instanceof Carbon ? $start->copy() : Carbon::parse($start))->setTimezone($tz)->locale($locale);
        $e = ($end instanceof Carbon ? $end->copy() : Carbon::parse($end))->setTimezone($tz)->locale($locale);

        $variant = 'different_year';
        if ($s->isSameDay($e)) {
            $variant = 'same_day';
        } elseif ($s->isSameYear($e) && $s->isSameMonth($e)) {
            $variant = 'same_month';
        } elseif ($s->isSameYear($e)) {
            $variant = 'same_year';
        }

        $template = date_range_template($style, $variant);

        $startMonthLongGenitive = ltrim((string) preg_replace('/^\d+\s+/u', '', $s->isoFormat('D MMMM')));
        $endMonthLongGenitive = ltrim((string) preg_replace('/^\d+\s+/u', '', $e->isoFormat('D MMMM')));

        return strtr($template, [
            ':start_day' => $s->translatedFormat('j'),
            ':end_day' => $e->translatedFormat('j'),
            ':year' => $s->translatedFormat('Y'),
            ':start_year' => $s->translatedFormat('Y'),
            ':end_year' => $e->translatedFormat('Y'),
            ':start_month_short' => $s->translatedFormat('M'),
            ':end_month_short' => $e->translatedFormat('M'),
            ':start_month_long' => $s->translatedFormat('F'),
            ':end_month_long' => $e->translatedFormat('F'),
            ':start_month_long_genitive' => $startMonthLongGenitive,
            ':end_month_long_genitive' => $endMonthLongGenitive,
        ]);
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

        $locale = app()->getLocale();
        $s = ($start instanceof Carbon ? $start->copy() : Carbon::parse($start))->setTimezone($tz)->locale($locale);
        $e = ($end instanceof Carbon ? $end->copy() : Carbon::parse($end))->setTimezone($tz)->locale($locale);

        if ($s->isSameDay($e)) {
            return $s->translatedFormat('j M Y').' · '.$s->format('H:i').' - '.$e->format('H:i');
        }

        if ($s->isSameYear($e) && $s->isSameMonth($e)) {
            return $s->translatedFormat('j M').' '.$s->format('H:i').' → '.$e->translatedFormat('j').' '.$e->format('H:i').', '.$s->translatedFormat('Y');
        }

        if ($s->isSameYear($e)) {
            return $s->translatedFormat('j M').' '.$s->format('H:i').' → '.$e->translatedFormat('j M').' '.$e->format('H:i').', '.$s->translatedFormat('Y');
        }

        return $s->translatedFormat('j M Y').' '.$s->format('H:i').' → '.$e->translatedFormat('j M Y').' '.$e->format('H:i');
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
        return format_date_range_localized($start, $end, 'compact');
    }
}

if (! function_exists('format_activity_duration_compact')) {
    /**
     * Human-readable duration from total minutes (e.g. "1 h 30 min", "45 min").
     */
    function format_activity_duration_compact(?int $totalMinutes): ?string
    {
        if ($totalMinutes === null || $totalMinutes < 1) {
            return null;
        }

        $h = intdiv($totalMinutes, 60);
        $m = $totalMinutes % 60;
        $parts = [];
        if ($h > 0) {
            $parts[] = __('ui.activities.duration_hours_part', ['n' => $h]);
        }
        if ($m > 0) {
            $parts[] = __('ui.activities.duration_minutes_part', ['n' => $m]);
        }

        return $parts === [] ? null : implode(' ', $parts);
    }
}

if (! function_exists('format_number')) {
    /**
     * Locale-aware number formatting using PHP's intl extension.
     * Falls back to a simple string cast if intl is not available.
     */
    function format_number(int|float|string|null $value, int $decimals = 0): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (! class_exists(\NumberFormatter::class)) {
            return (string) $value;
        }

        $formatter = new \NumberFormatter(app()->getLocale(), \NumberFormatter::DECIMAL);
        $formatter->setAttribute(\NumberFormatter::FRACTION_DIGITS, max(0, $decimals));

        $formatted = $formatter->format((float) $value);

        return $formatted === false ? (string) $value : $formatted;
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
