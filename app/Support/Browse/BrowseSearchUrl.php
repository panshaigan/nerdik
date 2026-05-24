<?php

declare(strict_types=1);

namespace App\Support\Browse;

use Illuminate\Http\Request;

/**
 * Named browse /search URLs for navigation and deep links.
 */
final class BrowseSearchUrl
{
    /** @var array<string, bool> */
    private const MY_EVENTS = [
        'include_past_events' => true,
        'only_events' => true,
        'only_mine' => true,
    ];

    /** @var array<string, bool> */
    private const MY_ACTIVITIES = [
        'include_past_events' => true,
        'only_mine' => true,
        'only_activities' => true,
    ];

    public static function myEvents(): string
    {
        return route('search.index', self::MY_EVENTS);
    }

    public static function myActivities(): string
    {
        return route('search.index', self::MY_ACTIVITIES);
    }

    public static function isMyEvents(Request $request): bool
    {
        return self::requestMatches($request, self::MY_EVENTS);
    }

    public static function isMyActivities(Request $request): bool
    {
        return self::requestMatches($request, self::MY_ACTIVITIES);
    }

    /**
     * Build a relative /search return URL from the active browse filter state.
     */
    public static function returnUrlFromFilterBag(BrowseListingFilterBag $bag, bool $mapView = false): string
    {
        $params = [];

        if ($bag->q !== '') {
            $params['q'] = $bag->q;
        }

        if ($bag->includePastEvents) {
            $params['include_past_events'] = true;
        }

        if ($bag->onlyEvents) {
            $params['only_events'] = true;
        } elseif ($bag->onlyActivities) {
            $params['only_activities'] = true;
        }

        if ($bag->onlyMine) {
            $params['only_mine'] = true;
        }

        if ($mapView) {
            $params['map_view'] = true;
        }

        if ($bag->tagsMatchAll) {
            $params['tags_match_all'] = true;
        }

        if ($bag->tagIds !== []) {
            $params['tag_ids'] = $bag->tagIds;
        }

        foreach ([
            'min_lat' => $bag->minLat,
            'max_lat' => $bag->maxLat,
            'min_lng' => $bag->minLng,
            'max_lng' => $bag->maxLng,
        ] as $key => $value) {
            if (filled($value)) {
                $params[$key] = $value;
            }
        }

        return self::relativeSearchUrl($params);
    }

    /**
     * Normalize a /search return URL so only_events and only_activities are never both enabled.
     */
    public static function normalizeReturnUrl(string $url): string
    {
        if (return_path_from_uri($url) !== '/search') {
            return $url;
        }

        $queryString = parse_url($url, PHP_URL_QUERY);
        if (! is_string($queryString) || $queryString === '') {
            return '/search';
        }

        parse_str($queryString, $query);

        $onlyEvents = self::queryBoolean($query, 'only_events');
        $onlyActivities = self::queryBoolean($query, 'only_activities');

        if ($onlyEvents && $onlyActivities) {
            $winner = self::resolveExclusiveKindFilter($queryString);
            if ($winner === 'events') {
                unset($query['only_activities']);
            } else {
                unset($query['only_events']);
            }
        }

        if (! self::queryBoolean($query, 'only_events')) {
            unset($query['only_events']);
        }

        if (! self::queryBoolean($query, 'only_activities')) {
            unset($query['only_activities']);
        }

        if ($query === []) {
            return '/search';
        }

        return '/search?'.http_build_query($query);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private static function relativeSearchUrl(array $params): string
    {
        $relative = route('search.index', $params, false);

        return str_starts_with($relative, '/') ? $relative : '/'.$relative;
    }

    /**
     * When both kind filters are present on a request, keep the one last set in the query bag.
     */
    public static function resolveExclusiveKindFilterFromRequest(Request $request): string
    {
        $last = 'events';

        foreach (array_keys($request->query()) as $key) {
            if ($key === 'only_events') {
                $last = 'events';
            } elseif ($key === 'only_activities') {
                $last = 'activities';
            }
        }

        return $last;
    }

    /**
     * When both kind filters appear in a query string, keep the one that appears last.
     */
    public static function resolveExclusiveKindFilter(string $queryString): string
    {
        $eventsPos = strpos($queryString, 'only_events');
        $activitiesPos = strpos($queryString, 'only_activities');

        if ($eventsPos === false) {
            return 'activities';
        }

        if ($activitiesPos === false) {
            return 'events';
        }

        return $eventsPos > $activitiesPos ? 'events' : 'activities';
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private static function queryBoolean(array $query, string $key): bool
    {
        if (! array_key_exists($key, $query)) {
            return false;
        }

        return filter_var($query[$key], FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @param  array<string, bool>  $expected
     */
    private static function requestMatches(Request $request, array $expected): bool
    {
        if (! $request->routeIs('search.index')) {
            return false;
        }

        foreach ($expected as $key => $value) {
            if ($request->boolean($key) !== $value) {
                return false;
            }
        }

        if (isset($expected['only_events']) && $request->boolean('only_activities')) {
            return false;
        }

        if (isset($expected['only_activities']) && $request->boolean('only_events')) {
            return false;
        }

        return true;
    }
}
