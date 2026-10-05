<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
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
        $clean = is_string($clean) ? $clean : '';

        return new HtmlString(self::enhanceImages($clean));
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

    private static function enhanceImages(string $html): string
    {
        if ($html === '' || ! str_contains(strtolower($html), '<img')) {
            return $html;
        }

        $document = new DOMDocument;
        $wrapped = '<?xml encoding="utf-8"?><div id="nerdik-rich-text-root">'.$html.'</div>';
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($loaded !== true) {
            return $html;
        }

        foreach ($document->getElementsByTagName('img') as $image) {
            if (! $image instanceof DOMElement) {
                continue;
            }

            if (! $image->hasAttribute('loading')) {
                $image->setAttribute('loading', 'lazy');
            }

            if (! $image->hasAttribute('decoding')) {
                $image->setAttribute('decoding', 'async');
            }

            if (! $image->hasAttribute('sizes')) {
                $image->setAttribute('sizes', '(max-width: 40rem) 100vw, 32rem');
            }
        }

        $root = $document->getElementById('nerdik-rich-text-root');
        if ($root === null) {
            return $html;
        }

        $inner = '';
        foreach ($root->childNodes as $child) {
            $inner .= $document->saveHTML($child);
        }

        return $inner;
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
