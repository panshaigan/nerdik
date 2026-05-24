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
        $this->assertStringNotContainsString('only_activities=1', $url);
    }

    public function test_my_activities_url_includes_expected_filters(): void
    {
        $url = BrowseSearchUrl::myActivities();

        $this->assertStringContainsString('/search?', $url);
        $this->assertStringContainsString('include_past_events=1', $url);
        $this->assertStringContainsString('only_activities=1', $url);
        $this->assertStringContainsString('only_mine=1', $url);
        $this->assertStringNotContainsString('only_events=1', $url);
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
}
