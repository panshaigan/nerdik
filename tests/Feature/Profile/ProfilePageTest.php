<?php

namespace Tests\Feature\Profile;

use App\Enums\TimeDisplayFormat;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfilePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/profile');

        $response
            ->assertOk()
            ->assertSeeVolt('profile.profile-tabs')
            ->assertSeeVolt('profile.update-identity-information-form')
            ->assertSee('data-ui="profile-identity-form"', false);
    }

    public function test_non_organizer_sees_organizer_request_on_identity_tab(): void
    {
        $user = User::factory()->create([
            'is_event_organizer' => false,
            'is_admin' => false,
        ]);

        $this->actingAs($user)
            ->get('/profile')
            ->assertOk()
            ->assertSee('data-ui="profile-organizer-request"', false)
            ->assertSee(__('ui.user_requests.request_organizer_access'), false);
    }

    public function test_event_organizer_does_not_see_organizer_request_on_identity_tab(): void
    {
        $user = User::factory()->organizer()->create([
            'is_admin' => false,
        ]);

        $this->actingAs($user)
            ->get('/profile')
            ->assertOk()
            ->assertDontSee('data-ui="profile-organizer-request"', false)
            ->assertDontSee(__('ui.user_requests.request_organizer_access'), false);
    }

    public function test_profile_page_fires_toast_from_oauth_linking_session(): void
    {
        $user = User::factory()->create();
        $message = __('ui.profile.oauth_link_facebook_success');

        $response = $this
            ->actingAs($user)
            ->withSession([
                'ui.toast' => [
                    'type' => 'success',
                    'title' => $message,
                ],
            ])
            ->get('/profile?tab=avatar');

        $response
            ->assertOk()
            ->assertSee($message, false)
            ->assertSee('window.toast', false);
    }

    public function test_app_layout_exposes_locale_and_time_display_format_for_datetime_pickers(): void
    {
        $user = User::factory()->create();
        $user->profile()->update([
            'time_display_format' => TimeDisplayFormat::TwelveHour,
        ]);

        $response = $this->actingAs($user)->get('/profile');

        $response
            ->assertOk()
            ->assertSee('data-time-display-format="12h"', false)
            ->assertSee('data-locale="'.app()->getLocale().'"', false)
            ->assertSee('data-datetime-minute-step=', false);
    }

    public function test_profile_page_has_tab_query_parameter(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/profile?tab=contact');

        $response->assertOk();
        $response->assertSee('data-ui="profile-tabs"', false);
    }

    public function test_app_layout_exposes_copy_to_clipboard_ui_strings(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/profile')
            ->assertOk()
            ->assertSee('window.__ui', false)
            ->assertSee(__('ui.common.copied'), false)
            ->assertSee(__('ui.common.copy_failed'), false);
    }
}
