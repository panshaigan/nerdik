<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\User;
use App\Models\UserRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Livewire\Volt\Volt;
use Tests\TestCase;

class NavigationMenuTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_does_not_see_create_links_in_navigation(): void
    {
        $this->get(route('search.index'))
            ->assertOk()
            ->assertSee(route('catalog.places'), false)
            ->assertSee(route('catalog.organizations'), false)
            ->assertSee(route('catalog.series'), false)
            ->assertDontSee(__('ui.nav.create_event'), false)
            ->assertDontSee(__('ui.nav.create_activity'), false);
    }

    public function test_guest_sees_login_and_register_in_navigation(): void
    {
        $this->get(route('search.index'))
            ->assertOk()
            ->assertSee(__('ui.nav.log_in'), false)
            ->assertSee(__('ui.nav.register'), false)
            ->assertSee(route('login'), false)
            ->assertSee(route('register'), false);
    }

    public function test_guest_brand_links_to_landing_page(): void
    {
        $response = $this->get(route('search.index'))
            ->assertOk();

        $content = $response->getContent();
        $landingUrl = preg_quote(url('/'), '/');

        $this->assertMatchesRegularExpression(
            '/href="'.$landingUrl.'"[^>]*class="[^"]*\bui-nav-brand\b/',
            $content,
        );

        $this->assertStringContainsString('ui-brand-name', $content);
        $this->assertStringContainsString((string) config('app.name'), $content);

        $response->assertDontSee('href="'.route('dashboard').'"', false);
    }

    public function test_authenticated_brand_links_to_dashboard(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk();

        $dashboardUrl = preg_quote(route('dashboard'), '/');

        $this->assertMatchesRegularExpression(
            '/href="'.$dashboardUrl.'"[^>]*class="[^"]*\bui-nav-brand\b/',
            $response->getContent(),
        );
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
            ->assertSee(__('ui.user_requests.request_organizer_access'), false)
            ->assertSee('data-ui="nav-request-organizer"', false)
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
            ->assertSee(__('ui.nav.my_organizations'), false)
            ->assertSee(__('ui.nav.create_activity'), false)
            ->assertDontSee(__('ui.user_requests.request_organizer_access'), false)
            ->assertSee(route('events.create'), false)
            ->assertSee(route('activities.create'), false);
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
            ->assertSee('ui-nav-brand', false)
            ->assertSee('ui-brand-name', false)
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
            ->assertDontSee('data-ui="nav-requests"', false)
            ->assertSee('data-ui="nav-account-settings"', false)
            ->assertSee('data-ui="nav-account-header"', false)
            ->assertSee(route('profile'), false)
            ->assertSee(__('ui.nav.account_settings'), false)
            ->assertDontSee(route('organizations.index'), false)
            ->assertSee(__('ui.user_requests.request_organizer_access'), false)
            ->assertSee('data-ui="nav-request-organizer"', false)
            ->assertDontSee(__('ui.me.menu_events'), false)
            ->assertSee(__('ui.me.menu_activities'), false)
            ->assertSee(__('Log Out'), false)
            ->assertDontSee('window.toggleTheme()', false);
    }

    public function test_navigation_uses_accessible_dialog_markup_and_unique_theme_toggle_ids(): void
    {
        $response = $this->get(route('search.index'))
            ->assertOk()
            ->assertSee('<div', false)
            ->assertSee('id="mobile-nav-drawer"', false)
            ->assertSee('role="dialog"', false)
            ->assertDontSee('<aside', false);

        preg_match_all(
            '/<input id="([^"]+)" type="checkbox" class="theme-controller/',
            $response->getContent(),
            $themeToggleIds,
        );

        $this->assertGreaterThanOrEqual(2, count($themeToggleIds[1]));
        $this->assertCount(
            count($themeToggleIds[1]),
            array_unique($themeToggleIds[1]),
        );
    }

    public function test_navigation_shows_requests_badge_for_pending_incoming_requests(): void
    {
        $host = User::factory()->create();
        $recipient = User::factory()->create();
        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'updated_by' => $host->id,
        ]);

        UserRequest::factory()->activityInvite()->create([
            'requester_id' => $host->id,
            'recipient_id' => $recipient->id,
            'subject_type' => 'activity',
            'subject_id' => $activity->id,
        ]);

        $response = $this->actingAs($recipient)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-ui="nav-requests"', false);

        $this->assertMatchesRegularExpression(
            '/data-ui="nav-requests"[\s\S]*?<span[^>]*>\s*1\s*<\/span>/',
            $response->getContent(),
        );
    }

    public function test_navigation_hides_requests_icon_when_user_has_no_requests(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('data-ui="nav-requests"', false);
    }

    public function test_admin_sees_ops_links_in_profile_menu(): void
    {
        Config::set('app.url', 'http://localhost');
        Config::set('app.environment_urls.production', 'https://nerdik.app');
        Config::set('app.environment_urls.staging', 'https://staging.nerdik.app');
        Config::set('app.environment_urls.development', 'http://localhost');
        Config::set('sentry.dashboard_url', 'https://sentry.example/org/project');
        Config::set('mail.support_mailbox_url', 'https://mail.example/inbox');
        Config::set('umami.dashboard_url', 'https://umami.example/website');
        Config::set('services.google.search_console_url', 'https://search.google.example/console');
        Config::set('mail.brevo_dashboard_url', 'https://brevo.example/logs');
        Config::set('services.adminer.url', 'https://adminer.example');
        Config::set('services.hosting_manager.url', 'https://hosting.example/dashboard');

        $admin = User::factory()->admin()->create();

        $filamentUrl = url('/'.trim((string) config('filament.admin_path'), '/'));
        $pulseUrl = url('/'.trim((string) config('pulse.path'), '/'));

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(__('ui.nav.admin_section'), false)
            ->assertSee(__('ui.nav.admin_panel'), false)
            ->assertSee(__('ui.nav.pulse'), false)
            ->assertSee(__('ui.nav.production'), false)
            ->assertSee(__('ui.nav.staging'), false)
            ->assertDontSee(__('ui.nav.development'), false)
            ->assertSee(__('ui.nav.sentry'), false)
            ->assertSee(__('ui.nav.support'), false)
            ->assertSee(__('ui.nav.umami'), false)
            ->assertSee(__('ui.nav.google_search_console'), false)
            ->assertSee(__('ui.nav.brevo'), false)
            ->assertSee(__('ui.nav.adminer'), false)
            ->assertSee(__('ui.nav.hosting_manager'), false)
            ->assertSee($filamentUrl, false)
            ->assertSee($pulseUrl, false)
            ->assertSee('https://nerdik.app', false)
            ->assertSee('https://staging.nerdik.app', false)
            ->assertSee('https://sentry.example/org/project', false)
            ->assertSee('https://mail.example/inbox', false)
            ->assertSee('https://umami.example/website', false)
            ->assertSee('https://search.google.example/console', false)
            ->assertSee('https://brevo.example/logs', false)
            ->assertSee('https://adminer.example', false)
            ->assertSee('https://hosting.example/dashboard', false);
    }

    public function test_non_admin_does_not_see_ops_links_in_profile_menu(): void
    {
        Config::set('app.environment_urls.production', 'https://nerdik.app');
        Config::set('app.environment_urls.staging', 'https://staging.nerdik.app');
        Config::set('app.environment_urls.development', 'http://localhost');
        Config::set('sentry.dashboard_url', 'https://sentry.example/org/project');
        Config::set('mail.support_mailbox_url', 'https://mail.example/inbox');
        Config::set('umami.dashboard_url', 'https://umami.example/website');
        Config::set('services.google.search_console_url', 'https://search.google.example/console');
        Config::set('mail.brevo_dashboard_url', 'https://brevo.example/logs');
        Config::set('services.adminer.url', 'https://adminer.example');
        Config::set('services.hosting_manager.url', 'https://hosting.example/dashboard');

        $user = User::factory()->organizer()->create([
            'is_admin' => false,
        ]);

        $filamentUrl = url('/'.trim((string) config('filament.admin_path'), '/'));
        $pulseUrl = url('/'.trim((string) config('pulse.path'), '/'));

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee(__('ui.nav.admin_panel'), false)
            ->assertDontSee(__('ui.nav.pulse'), false)
            ->assertDontSee(__('ui.nav.production'), false)
            ->assertDontSee(__('ui.nav.staging'), false)
            ->assertDontSee(__('ui.nav.development'), false)
            ->assertDontSee(__('ui.nav.sentry'), false)
            ->assertDontSee(__('ui.nav.support'), false)
            ->assertDontSee(__('ui.nav.umami'), false)
            ->assertDontSee(__('ui.nav.google_search_console'), false)
            ->assertDontSee(__('ui.nav.brevo'), false)
            ->assertDontSee(__('ui.nav.adminer'), false)
            ->assertDontSee(__('ui.nav.hosting_manager'), false)
            ->assertDontSee($filamentUrl, false)
            ->assertDontSee($pulseUrl, false)
            ->assertDontSee('https://nerdik.app', false)
            ->assertDontSee('https://staging.nerdik.app', false)
            ->assertDontSee('https://sentry.example/org/project', false)
            ->assertDontSee('https://mail.example/inbox', false)
            ->assertDontSee('https://umami.example/website', false)
            ->assertDontSee('https://search.google.example/console', false)
            ->assertDontSee('https://brevo.example/logs', false)
            ->assertDontSee('https://adminer.example', false)
            ->assertDontSee('https://hosting.example/dashboard', false);
    }

    public function test_admin_ops_menu_hides_optional_links_when_unconfigured(): void
    {
        Config::set('app.environment_urls.production', null);
        Config::set('app.environment_urls.staging', null);
        Config::set('app.environment_urls.development', null);
        Config::set('sentry.dashboard_url', null);
        Config::set('mail.support_mailbox_url', null);
        Config::set('umami.dashboard_url', null);
        Config::set('services.google.search_console_url', null);
        Config::set('mail.brevo_dashboard_url', null);
        Config::set('services.adminer.url', null);
        Config::set('services.hosting_manager.url', null);

        $admin = User::factory()->admin()->create();

        $filamentUrl = url('/'.trim((string) config('filament.admin_path'), '/'));
        $pulseUrl = url('/'.trim((string) config('pulse.path'), '/'));

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(__('ui.nav.admin_panel'), false)
            ->assertSee(__('ui.nav.pulse'), false)
            ->assertSee($filamentUrl, false)
            ->assertSee($pulseUrl, false)
            ->assertDontSee(__('ui.nav.production'), false)
            ->assertDontSee(__('ui.nav.staging'), false)
            ->assertDontSee(__('ui.nav.development'), false)
            ->assertDontSee(__('ui.nav.sentry'), false)
            ->assertDontSee(__('ui.nav.support'), false)
            ->assertDontSee(__('ui.nav.umami'), false)
            ->assertDontSee(__('ui.nav.google_search_console'), false)
            ->assertDontSee(__('ui.nav.brevo'), false)
            ->assertDontSee(__('ui.nav.adminer'), false)
            ->assertDontSee(__('ui.nav.hosting_manager'), false);
    }
}
