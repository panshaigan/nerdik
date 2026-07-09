<?php

declare(strict_types=1);

namespace App\Support\Browse;

use Illuminate\Http\Request;

/**
 * Session-backed last /search filter URL for restoring browse state.
 */
final class BrowseSearchState
{
    public const SESSION_KEY = 'browse.search.last_url';

    public static function remember(?string $url): void
    {
        $safe = safe_return_url($url);
        if ($safe === null || return_path_from_uri($safe) !== '/search') {
            return;
        }

        if (BrowseSearchUrl::urlHasEphemeralPreset($safe)) {
            return;
        }

        $normalized = BrowseSearchUrl::normalizeReturnUrl($safe);
        if ($normalized === '/search') {
            self::forget();

            return;
        }

        session([self::SESSION_KEY => $normalized]);
    }

    public static function forget(): void
    {
        session()->forget(self::SESSION_KEY);
    }

    public static function cached(): ?string
    {
        $url = safe_return_url(session(self::SESSION_KEY));
        if ($url === null || return_path_from_uri($url) !== '/search') {
            return null;
        }

        $normalized = BrowseSearchUrl::normalizeReturnUrl($url);

        return $normalized === '/search' ? null : $normalized;
    }

    public static function indexUrl(): string
    {
        return self::cached() ?? route('search.index', [], false) ?: '/search';
    }

    public static function requestHasSearchParams(Request $request): bool
    {
        if (trim((string) $request->input('q', '')) !== '') {
            return true;
        }

        $tagIds = $request->input('tag_ids', []);
        if (is_array($tagIds) && $tagIds !== []) {
            return true;
        }

        foreach ([
            'tags_match_all',
            'include_past_events',
            'only_events',
            'only_activities',
            'only_mine',
            'only_free_places',
            'map_view',
        ] as $key) {
            if (filter_var($request->input($key, false), FILTER_VALIDATE_BOOLEAN)) {
                return true;
            }
        }

        foreach (['min_lat', 'max_lat', 'min_lng', 'max_lng', 'from_date', 'to_date'] as $key) {
            if (filled($request->input($key))) {
                return true;
            }
        }

        $sort = (string) $request->input('sort', 'date');
        if ($sort !== '' && $sort !== 'date') {
            return true;
        }

        $sortDir = strtolower((string) $request->input('sort_dir', 'asc'));
        if ($sortDir !== '' && $sortDir !== 'asc') {
            return true;
        }

        return false;
    }

    public static function syncFromFilterBag(
        BrowseListingFilterBag $bag,
        bool $mapView = false,
        string $sort = 'date',
        string $sortDir = 'asc',
    ): void {
        $url = BrowseSearchUrl::returnUrlFromFilterBag($bag, $mapView, $sort, $sortDir);
        if ($url === '/search') {
            self::forget();

            return;
        }

        self::remember($url);
    }
}
