<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class NavigationMenuTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_does_not_see_create_links_in_main_navigation(): void
    {
        $this->get(route('search.index'))
            ->assertOk()
            ->assertDontSee(__('ui.nav.create_event'), false)
            ->assertDontSee(__('ui.nav.create_activity'), false);
    }

    public function test_logged_in_user_sees_create_activity_but_not_create_event_when_not_organizer(): void
    {
        $user = User::factory()->create([
            'is_admin' => false,
            'is_event_organizer' => false,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee(__('ui.nav.create_event'), false)
            ->assertSee(__('ui.nav.create_activity'), false)
            ->assertSee(route('activities.create'), false);
    }

    public function test_event_organizer_sees_create_event_and_create_activity_in_main_navigation(): void
    {
        $user = User::factory()->organizer()->create([
            'is_admin' => false,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(__('ui.nav.create_event'), false)
            ->assertSee(__('ui.nav.create_activity'), false)
            ->assertSee(route('events.create'), false)
            ->assertSee(route('activities.create'), false);
    }

    public function test_navigation_avatar_url_updates_on_profile_avatar_updated_event(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $avatarUrl = 'https://example.test/storage/avatars/'.$user->id.'.webp?v=123456';

        Volt::test('layout.navigation')
            ->dispatch('profile-avatar-updated', avatarUrl: $avatarUrl)
            ->assertSet('navAvatarUrl', $avatarUrl);
    }

    public function test_authenticated_mobile_drawer_includes_account_and_notification_links(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('id="mobile-nav-drawer"', false)
            ->assertSee(route('notifications.index'), false)
            ->assertSee(route('organizations.index'), false)
            ->assertSee(__('ui.me.menu_events'), false)
            ->assertSee(__('ui.me.menu_activities'), false)
            ->assertSee(__('Log Out'), false)
            ->assertDontSee('window.toggleTheme()', false);
    }
}
