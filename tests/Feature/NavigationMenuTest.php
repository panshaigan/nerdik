<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class NavigationMenuTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_does_not_see_create_links_in_navigation(): void
    {
        $this->get(route('search.index'))
            ->assertOk()
            ->assertDontSee(__('ui.nav.create_event'), false)
            ->assertDontSee(__('ui.nav.create_activity'), false);
    }

    public function test_logged_in_user_sees_create_activity_in_profile_menu_but_not_create_event_when_not_organizer(): void
    {
        $user = User::factory()->create([
            'is_admin' => false,
            'is_event_organizer' => false,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee(__('ui.nav.create_event'), false)
            ->assertDontSee(__('ui.me.menu_events'), false)
            ->assertDontSee(__('ui.user_requests.request_organizer_access'), false)
            ->assertSee(__('ui.nav.create_activity'), false)
            ->assertSee(route('activities.create'), false);
    }

    public function test_event_organizer_sees_create_event_and_create_activity_in_profile_menu(): void
    {
        $user = User::factory()->organizer()->create([
            'is_admin' => false,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(__('ui.nav.create_event'), false)
            ->assertSee(__('ui.me.menu_events'), false)
            ->assertSee(__('ui.nav.create_activity'), false)
            ->assertDontSee(__('ui.user_requests.request_organizer_access'), false)
            ->assertSee(route('events.create'), false)
            ->assertSee(route('activities.create'), false);
    }

    public function test_navigation_search_link_includes_magnifying_glass_icon(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk();

        $this->assertMatchesRegularExpression(
            '/<a[^>]*ui-nav-link[^>]*gap-1\.5[^>]*>[\s\S]*?<svg/s',
            $response->getContent(),
        );
    }

    public function test_navigation_app_name_has_active_nav_link_state_on_dashboard(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk();

        $this->assertMatchesRegularExpression(
            '/class="[^"]*(?=.*\bui-nav-brand-name\b)(?=.*\bis-active\b)[^"]*"/',
            $response->getContent(),
        );
    }

    public function test_navigation_does_not_include_dashboard_menu_item(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk();

        $this->assertDoesNotMatchRegularExpression(
            '/ui-nav-link[^>]*>\s*'.preg_quote(__('ui.nav.dashboard'), '/').'\s*<\/a>/',
            $response->getContent(),
        );
    }

    public function test_navigation_avatar_url_updates_on_profile_avatar_updated_event(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $pendingUrl = 'https://example.test/storage/media/1/pending.webp?v=123456';

        Volt::test('layout.navigation')
            ->set('navAvatarUrl', 'https://example.test/storage/avatars/'.$user->id.'.webp?v=old')
            ->dispatch('profile-avatar-updated', avatarUrl: $pendingUrl)
            ->assertSet('navAvatarUrl', $pendingUrl);

        Volt::test('layout.navigation')
            ->set('navAvatarUrl', $pendingUrl)
            ->dispatch('profile-avatar-updated')
            ->assertSet('navAvatarUrl', null);
    }

    public function test_navigation_shows_branded_app_name_next_to_logo(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('ui-nav-brand-name', false)
            ->assertSee(config('app.name'), false);
    }

    public function test_authenticated_mobile_drawer_includes_account_and_notification_links(): void
    {
        $user = User::factory()->create([
            'is_event_organizer' => false,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('id="mobile-nav-drawer"', false)
            ->assertSee(route('notifications.index'), false)
            ->assertSee(route('organizations.index'), false)
            ->assertDontSee(__('ui.user_requests.request_organizer_access'), false)
            ->assertDontSee(__('ui.me.menu_events'), false)
            ->assertSee(__('ui.me.menu_activities'), false)
            ->assertSee(__('Log Out'), false)
            ->assertDontSee('window.toggleTheme()', false);
    }

    public function test_navigation_is_fixed_with_layout_spacer(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk();

        $this->assertMatchesRegularExpression(
            '/<div class="[^"]*\bfixed\b[^"]*\btop-0\b[^"]*\binset-x-0\b[^"]*" role="navigation"/',
            $response->getContent(),
        );

        $response->assertSee('ui-app-navigation__spacer', false);
    }
}
