<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Browse;

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
}
