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
