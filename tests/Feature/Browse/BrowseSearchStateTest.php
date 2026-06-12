<?php

declare(strict_types=1);

namespace Tests\Feature\Browse;

use App\Livewire\Browse\BrowseEvents;
use App\Support\Browse\BrowseSearchState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Livewire\Livewire;
use Tests\TestCase;

final class BrowseSearchStateTest extends TestCase
{
    use RefreshDatabase;

    public function test_bare_search_redirects_to_cached_session_url(): void
    {
        $this->withSession([
            BrowseSearchState::SESSION_KEY => '/search?q=cached-term',
        ])
            ->get(route('search.index'))
            ->assertRedirect('/search?q=cached-term');
    }

    public function test_browse_events_mount_redirects_when_session_has_cached_url(): void
    {
        $this->withSession([
            BrowseSearchState::SESSION_KEY => '/search?q=cached-term',
        ]);

        Livewire::test(BrowseEvents::class)
            ->assertRedirect('/search?q=cached-term');
    }

    public function test_explicit_search_params_do_not_trigger_restore_redirect(): void
    {
        Livewire::test(BrowseEvents::class, ['q' => 'explicit'])
            ->assertOk()
            ->assertSet('q', 'explicit');
    }

    public function test_render_persists_filter_state_to_session(): void
    {
        Livewire::test(BrowseEvents::class, ['q' => 'persist-me'])
            ->assertOk();

        $this->assertSame('/search?q=persist-me', session(BrowseSearchState::SESSION_KEY));
    }

    public function test_forget_clears_cached_search_url(): void
    {
        session([BrowseSearchState::SESSION_KEY => '/search?q=cleared']);

        BrowseSearchState::forget();

        $this->assertNull(session(BrowseSearchState::SESSION_KEY));
    }

    public function test_request_has_search_params_ignores_page_only(): void
    {
        $request = Request::create('/search?page=2');

        $this->assertFalse(BrowseSearchState::requestHasSearchParams($request));
    }

    public function test_index_url_returns_cached_search_url(): void
    {
        session([BrowseSearchState::SESSION_KEY => '/search?q=nav']);

        $this->assertSame('/search?q=nav', BrowseSearchState::indexUrl());
    }

    public function test_index_url_falls_back_to_bare_search(): void
    {
        $this->assertSame('/search', BrowseSearchState::indexUrl());
    }

    public function test_remember_forgets_session_when_url_is_bare_search(): void
    {
        session([BrowseSearchState::SESSION_KEY => '/search?q=old']);

        BrowseSearchState::remember('/search');

        $this->assertNull(session(BrowseSearchState::SESSION_KEY));
    }
}
