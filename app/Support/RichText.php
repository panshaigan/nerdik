<?php

namespace App\Support;

use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Stevebauman\Purify\Facades\Purify;

final class RichText
{
    /**
     * Sanitize HTML from TinyMCE (or untrusted sources) before persisting.
     * Returns null when there is no meaningful content.
     */
    public static function sanitize(?string $html): ?string
    {
        if ($html === null) {
            return null;
        }

        $html = trim($html);
        if ($html === '') {
            return null;
        }

        $clean = Purify::config('tinymce')->clean($html);
        $clean = is_string($clean) ? trim($clean) : '';

        if ($clean === '' || self::isEffectivelyEmptyHtml($clean)) {
            return null;
        }

        return $clean;
    }

    /**
     * Safe HTML for Blade: purify again on output, then wrap for unescaped rendering.
     */
    public static function html(?string $stored): HtmlString
    {
        if ($stored === null || $stored === '') {
            return new HtmlString('');
        }

        $clean = Purify::config('tinymce')->clean($stored);

        return new HtmlString(is_string($clean) ? $clean : '');
    }

    /**
     * Plain-text excerpt (e.g. cards). HTML is stripped after purification.
     */
    public static function excerpt(?string $stored, int $limit = 120): string
    {
        if ($stored === null || $stored === '') {
            return '';
        }

        $clean = Purify::config('tinymce')->clean($stored);
        $plain = trim(strip_tags(is_string($clean) ? $clean : ''));

        return Str::limit($plain, $limit);
    }

    private static function isEffectivelyEmptyHtml(string $html): bool
    {
        $t = trim($html);

        return $t === '' || in_array($t, [
            '<p><br></p>',
            '<p></p>',
            '<br>',
            '<p><br /></p>',
        ], true);
    }
}
