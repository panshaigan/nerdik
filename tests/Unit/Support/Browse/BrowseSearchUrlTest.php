<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Browse;

use App\Support\Browse\BrowseListingFilterBag;
use App\Support\Browse\BrowseSearchUrl;
use Illuminate\Http\Request;
use Tests\TestCase;

final class BrowseSearchUrlTest extends TestCase
{
    public function test_my_events_url_includes_expected_filters(): void
    {
        $url = BrowseSearchUrl::myEvents();

        $this->assertStringContainsString('/search?', $url);
        $this->assertStringContainsString('include_past_events=1', $url);
        $this->assertStringContainsString('only_events=1', $url);
        $this->assertStringContainsString('only_mine=1', $url);
        $this->assertStringContainsString('preset='.BrowseSearchUrl::PRESET_MY_EVENTS, $url);
        $this->assertStringNotContainsString('only_activities=1', $url);
    }

    public function test_my_activities_url_includes_expected_filters(): void
    {
        $url = BrowseSearchUrl::myActivities();

        $this->assertStringContainsString('/search?', $url);
        $this->assertStringContainsString('include_past_events=1', $url);
        $this->assertStringContainsString('only_activities=1', $url);
        $this->assertStringContainsString('only_mine=1', $url);
        $this->assertStringContainsString('preset='.BrowseSearchUrl::PRESET_MY_ACTIVITIES, $url);
        $this->assertStringNotContainsString('only_events=1', $url);
    }

    public function test_is_ephemeral_preset_matches_profile_menu_urls(): void
    {
        $myEvents = Request::create(BrowseSearchUrl::myEvents());
        $myEvents->setRouteResolver(fn () => app('router')->getRoutes()->getByName('search.index'));

        $this->assertTrue(BrowseSearchUrl::isEphemeralPreset($myEvents));

        $myActivities = Request::create(BrowseSearchUrl::myActivities());
        $myActivities->setRouteResolver(fn () => app('router')->getRoutes()->getByName('search.index'));

        $this->assertTrue(BrowseSearchUrl::isEphemeralPreset($myActivities));
    }

    public function test_url_has_ephemeral_preset_detects_preset_query_param(): void
    {
        $this->assertTrue(BrowseSearchUrl::urlHasEphemeralPreset(BrowseSearchUrl::myEvents()));
        $this->assertFalse(BrowseSearchUrl::urlHasEphemeralPreset('/search?q=term'));
    }

    public function test_is_my_events_matches_search_preset(): void
    {
        $request = Request::create(BrowseSearchUrl::myEvents());
        $request->setRouteResolver(fn () => app('router')->getRoutes()->getByName('search.index'));

        $this->assertTrue(BrowseSearchUrl::isMyEvents($request));
        $this->assertFalse(BrowseSearchUrl::isMyActivities($request));
    }

    public function test_is_my_activities_matches_search_preset(): void
    {
        $request = Request::create(BrowseSearchUrl::myActivities());
        $request->setRouteResolver(fn () => app('router')->getRoutes()->getByName('search.index'));

        $this->assertTrue(BrowseSearchUrl::isMyActivities($request));
        $this->assertFalse(BrowseSearchUrl::isMyEvents($request));
    }

    public function test_return_url_from_filter_bag_keeps_only_one_kind_filter(): void
    {
        $bag = new BrowseListingFilterBag(
            q: '',
            tagIds: [],
            tagsMatchAll: false,
            includePastEvents: true,
            onlyEvents: true,
            onlyActivities: false,
            onlyMine: true,
            minLat: null,
            maxLat: null,
            minLng: null,
            maxLng: null,
        );

        $url = BrowseSearchUrl::returnUrlFromFilterBag($bag);

        $this->assertStringContainsString('only_events=1', $url);
        $this->assertStringNotContainsString('only_activities=1', $url);
    }

    public function test_normalize_return_url_drops_conflicting_kind_filter_using_last_query_param(): void
    {
        $url = BrowseSearchUrl::normalizeReturnUrl(
            '/search?include_past_events=1&only_events=1&only_activities=1&only_mine=1'
        );

        $this->assertStringContainsString('only_activities=1', $url);
        $this->assertStringNotContainsString('only_events=1', $url);
    }

    public function test_filter_bag_from_request_resolves_conflicting_kind_filters(): void
    {
        $request = Request::create('/search?only_events=1&only_activities=1');
        $bag = BrowseListingFilterBag::fromRequest($request);

        $this->assertFalse($bag->onlyEvents);
        $this->assertTrue($bag->onlyActivities);
    }

    public function test_return_url_from_filter_bag_includes_non_default_sort(): void
    {
        $bag = new BrowseListingFilterBag(
            q: '',
            tagIds: [],
            tagsMatchAll: false,
            includePastEvents: false,
            onlyEvents: false,
            onlyActivities: false,
            onlyMine: false,
            minLat: null,
            maxLat: null,
            minLng: null,
            maxLng: null,
        );

        $url = BrowseSearchUrl::returnUrlFromFilterBag($bag, false, 'name', 'desc');

        $this->assertStringContainsString('sort=name', $url);
        $this->assertStringContainsString('sort_dir=desc', $url);
    }

    public function test_return_url_from_filter_bag_omits_default_sort(): void
    {
        $bag = new BrowseListingFilterBag(
            q: 'term',
            tagIds: [],
            tagsMatchAll: false,
            includePastEvents: false,
            onlyEvents: false,
            onlyActivities: false,
            onlyMine: false,
            minLat: null,
            maxLat: null,
            minLng: null,
            maxLng: null,
        );

        $url = BrowseSearchUrl::returnUrlFromFilterBag($bag);

        $this->assertStringContainsString('q=term', $url);
        $this->assertStringNotContainsString('sort=', $url);
        $this->assertStringNotContainsString('sort_dir=', $url);
    }
}
