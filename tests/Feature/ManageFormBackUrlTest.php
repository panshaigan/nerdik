<?php

namespace Tests\Feature;

use App\Livewire\Events\ManageEventForm;
use App\Models\Activity;
use App\Models\Event;
use App\Models\User;
use App\Support\Ui\ManageFormBackUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ManageFormBackUrlTest extends TestCase
{
    use RefreshDatabase;

    public function test_safe_return_url_accepts_internal_paths(): void
    {
        $this->assertSame('/search', safe_return_url('/search'));
        $this->assertSame('/events/foo?tab=plan', safe_return_url('/events/foo?tab=plan'));
        $this->assertSame('/search?only_mine=true', safe_return_url('http://localhost/search?only_mine=true'));
    }

    public function test_safe_return_url_rejects_external_urls(): void
    {
        $this->assertNull(safe_return_url('https://evil.test'));
        $this->assertNull(safe_return_url('//evil.test'));
        $this->assertNull(safe_return_url('/livewire-9ee9781d/update'));
        $this->assertNull(safe_return_url(null));
        $this->assertNull(safe_return_url(''));
    }

    public function test_url_with_return_appends_query_param(): void
    {
        $url = url_with_return('http://example.test/activities/create', '/search');

        $this->assertStringContainsString('return=%2Fsearch', $url);
    }

    public function test_url_with_return_uses_referer_for_livewire_requests(): void
    {
        $request = request()->create('/livewire-9ee9781d/update', 'POST', server: [
            'HTTP_HOST' => 'localhost',
            'HTTP_REFERER' => 'http://localhost/search?include_past_events=true&only_events=true&only_mine=true',
        ]);
        app()->instance('request', $request);

        $url = url_with_return('http://localhost/events/demo/edit');

        $this->assertStringContainsString(
            'return=%2Fsearch%3Finclude_past_events%3Dtrue%26only_events%3Dtrue%26only_mine%3Dtrue',
            $url
        );
    }

    public function test_manage_form_back_url_uses_session_when_return_query_is_livewire_path(): void
    {
        $user = User::factory()->create([
            'is_event_organizer' => true,
        ]);
        $event = Event::factory()->create([
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $this->withSession(['browsing.return' => '/dashboard'])
            ->actingAs($user)
            ->get(route('events.edit', $event).'?return='.rawurlencode('/livewire-9ee9781d/update'))
            ->assertOk()
            ->assertSee(route('events.show', $event), false);
    }

    public function test_event_edit_back_link_keeps_return_url_after_tab_change(): void
    {
        $user = User::factory()->create([
            'is_event_organizer' => true,
        ]);
        $event = Event::factory()->create([
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        Livewire::actingAs($user)
            ->withQueryParams(['return' => '/search?only_events=1&only_mine=1'])
            ->test(ManageEventForm::class, ['event' => $event])
            ->assertSet('tab', 'main-details')
            ->assertSee('href="/search?only_events=1&amp;only_mine=1"', false)
            ->set('tab', 'location')
            ->assertSet('tab', 'location')
            ->assertSee('href="/search?only_events=1&amp;only_mine=1"', false);

        $this->assertSame('/search?only_events=1&only_mine=1', session(ManageFormBackUrl::SESSION_KEY));
    }

    public function test_activity_edit_back_link_uses_return_query_param(): void
    {
        $user = User::factory()->create();
        $activity = Activity::factory()->create([
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(route('activities.edit', $activity).'?return='.rawurlencode('/search'))
            ->assertOk()
            ->assertSee('data-ui="page-header-back"', false)
            ->assertSee('href="/search"', false);
    }

    public function test_activity_edit_back_link_falls_back_to_show_page(): void
    {
        $user = User::factory()->create();
        $activity = Activity::factory()->create([
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(route('activities.edit', $activity))
            ->assertOk()
            ->assertSee('data-ui="page-header-back"', false)
            ->assertSee(route('activities.show', $activity), false);
    }

    public function test_activity_create_back_link_falls_back_to_search(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('activities.create'))
            ->assertOk()
            ->assertSee('data-ui="page-header-back"', false)
            ->assertSee(route('search.index'), false);
    }

    public function test_event_edit_back_link_uses_return_query_param(): void
    {
        $user = User::factory()->create([
            'is_event_organizer' => true,
        ]);
        $event = Event::factory()->create([
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(route('events.edit', $event).'?return='.rawurlencode('/search'))
            ->assertOk()
            ->assertSee('data-ui="page-header-back"', false)
            ->assertSee('href="/search"', false);
    }

    public function test_event_create_back_link_falls_back_to_search(): void
    {
        $user = User::factory()->create([
            'is_event_organizer' => true,
        ]);

        $this->actingAs($user)
            ->get(route('events.create'))
            ->assertOk()
            ->assertSee('data-ui="page-header-back"', false)
            ->assertSee(route('search.index'), false);
    }

    public function test_browse_listing_card_edit_url_includes_return_param(): void
    {
        $user = User::factory()->create();
        $activity = Activity::factory()->create([
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->get('/search')
            ->assertOk()
            ->assertSee(rawurlencode('/search'), false);
    }
}
