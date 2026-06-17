<?php

namespace Tests\Feature\Auth;

use App\Enums\AvatarSource;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FacebookAuthControllerTest extends TestCase
{
    use RefreshDatabase;

    private function fakeFacebookUser(
        string $id = '123456789',
        ?string $email = 'jane@example.com',
        string $name = 'Jane Doe',
        ?string $link = null,
    ): SocialiteUser {
        $facebookUser = new SocialiteUser;
        $raw = [
            'id' => $id,
            'name' => $name,
            'email' => $email,
        ];

        if ($link !== null) {
            $raw['link'] = $link;
        }

        $facebookUser->setRaw($raw)->map([
            'id' => $id,
            'name' => $name,
            'email' => $email,
            'avatar' => null,
            'profileUrl' => $link,
        ]);
        $facebookUser->token = 'fake-token';

        return $facebookUser;
    }

    private function mockSocialiteWith(SocialiteUser $facebookUser): void
    {
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('scopes')->with(['email', 'public_profile'])->andReturnSelf();
        $provider->shouldReceive('fields')->with(['name', 'email', 'link', 'short_name', 'picture.width(1920)'])->andReturnSelf();
        $provider->shouldReceive('user')->andReturn($facebookUser);

        Socialite::shouldReceive('driver')->with('facebook')->andReturn($provider);
    }

    private function mockSocialiteRedirect(): void
    {
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('scopes')->with(['email', 'public_profile'])->andReturnSelf();
        $provider->shouldReceive('fields')->with(['name', 'email', 'link', 'short_name', 'picture.width(1920)'])->andReturnSelf();
        $provider->shouldReceive('redirect')->andReturn(redirect('https://facebook.com/oauth'));

        Socialite::shouldReceive('driver')->with('facebook')->andReturn($provider);
    }

    #[Test]
    public function authenticated_user_can_start_facebook_redirect_for_contact_linking(): void
    {
        $user = User::factory()->create();

        $this->mockSocialiteRedirect();

        $response = $this->actingAs($user)->get(route('facebook.redirect', ['return_tab' => 'contact']));

        $response->assertRedirect('https://facebook.com/oauth');
        $response->assertCookie('oauth_link_user_id', (string) $user->id);
        $this->assertSame($user->id, session('socialite.link_user_id'));
        $this->assertSame('contact', session('socialite.return_tab'));
    }

    #[Test]
    public function callback_links_facebook_from_contact_tab_without_changing_avatar_source(): void
    {
        $user = User::factory()->create([
            'email' => 'contact-linker@example.com',
        ]);
        $user->profile()->update([
            'avatar_source' => AvatarSource::Generated,
        ]);

        $this->mockSocialiteWith($this->fakeFacebookUser(
            id: 'fb-contact-link',
            email: 'contact-linker@example.com',
        ));

        $response = $this
            ->withSession([
                'socialite.link_user_id' => $user->id,
                'socialite.return_tab' => 'contact',
            ])
            ->get(route('facebook.callback'));

        $response->assertRedirect(route('profile', absolute: false).'?tab=contact');
        $response->assertSessionHas('ui.toast', [
            'type' => 'success',
            'title' => __('ui.profile.oauth_link_facebook_success'),
        ]);
        $this->assertAuthenticatedAs($user);
        $this->assertSame('fb-contact-link', $user->fresh()->profile?->facebook_id);
        $this->assertSame(AvatarSource::Generated, $user->fresh()->profile?->avatar_source);
    }

    #[Test]
    public function authenticated_user_can_start_facebook_redirect_for_avatar_linking(): void
    {
        $user = User::factory()->create();

        $this->mockSocialiteRedirect();

        $response = $this->actingAs($user)->get(route('facebook.redirect', ['return_tab' => 'avatar']));

        $response->assertRedirect('https://facebook.com/oauth');
        $response->assertCookie('oauth_link_user_id', (string) $user->id);
        $this->assertSame($user->id, session('socialite.link_user_id'));
        $this->assertSame('avatar', session('socialite.return_tab'));
    }

    #[Test]
    public function callback_links_facebook_to_authenticated_user_when_linking_from_profile(): void
    {
        $user = User::factory()->create([
            'email' => 'linker@example.com',
        ]);

        $this->mockSocialiteWith($this->fakeFacebookUser(
            id: 'fb-link-1',
            email: 'linker@example.com',
        ));

        $response = $this
            ->withSession([
                'socialite.link_user_id' => $user->id,
                'socialite.return_tab' => 'avatar',
            ])
            ->get(route('facebook.callback'));

        $response->assertRedirect(route('profile', absolute: false).'?tab=avatar');
        $this->assertAuthenticatedAs($user);
        $this->assertSame('fb-link-1', $user->fresh()->profile?->facebook_id);
        $this->assertSame(AvatarSource::Facebook, $user->fresh()->profile?->avatar_source);
    }

    #[Test]
    public function callback_links_facebook_when_email_differs_from_account_during_profile_linking(): void
    {
        $user = User::factory()->create([
            'email' => 'local@example.com',
        ]);

        $this->mockSocialiteWith($this->fakeFacebookUser(
            id: 'fb-mismatch',
            email: 'other@example.com',
        ));

        $response = $this
            ->withSession([
                'socialite.link_user_id' => $user->id,
                'socialite.return_tab' => 'avatar',
            ])
            ->get(route('facebook.callback'));

        $response->assertRedirect(route('profile', absolute: false).'?tab=avatar');
        $response->assertSessionHas('ui.toast', [
            'type' => 'success',
            'title' => __('ui.profile.oauth_link_facebook_success'),
        ]);
        $this->assertAuthenticatedAs($user);
        $this->assertSame('fb-mismatch', $user->fresh()->profile?->facebook_id);
        $this->assertSame('other@example.com', $user->fresh()->profile?->facebook_email);
        $this->assertSame(AvatarSource::Facebook, $user->fresh()->profile?->avatar_source);
    }

    #[Test]
    public function callback_links_facebook_using_cookie_when_session_link_user_id_is_missing(): void
    {
        $user = User::factory()->create([
            'email' => 'local@example.com',
        ]);

        $this->mockSocialiteWith($this->fakeFacebookUser(
            id: 'fb-cookie-link',
            email: 'other@example.com',
        ));

        $response = $this
            ->withCookie('oauth_link_user_id', (string) $user->id)
            ->get(route('facebook.callback'));

        $response->assertRedirect(route('profile', absolute: false).'?tab=avatar');
        $this->assertAuthenticatedAs($user);
        $this->assertSame('fb-cookie-link', $user->fresh()->profile?->facebook_id);
    }

    #[Test]
    public function callback_links_facebook_without_email_when_provider_id_is_present(): void
    {
        $user = User::factory()->create([
            'email' => 'local@example.com',
        ]);

        $this->mockSocialiteWith($this->fakeFacebookUser(
            id: 'fb-no-email',
            email: null,
        ));

        $response = $this
            ->withSession([
                'socialite.link_user_id' => $user->id,
                'socialite.return_tab' => 'avatar',
            ])
            ->get(route('facebook.callback'));

        $response->assertRedirect(route('profile', absolute: false).'?tab=avatar');
        $this->assertSame('fb-no-email', $user->fresh()->profile?->facebook_id);
    }

    #[Test]
    public function callback_rejects_facebook_linking_when_id_belongs_to_another_user(): void
    {
        $other = User::factory()->create();
        $other->profile()->update(['facebook_id' => 'taken-fb-id']);

        $user = User::factory()->create([
            'email' => 'linker@example.com',
        ]);

        $this->mockSocialiteWith($this->fakeFacebookUser(
            id: 'taken-fb-id',
            email: 'linker@example.com',
        ));

        $response = $this
            ->withSession([
                'socialite.link_user_id' => $user->id,
                'socialite.return_tab' => 'avatar',
            ])
            ->get(route('facebook.callback'));

        $response->assertRedirect(route('profile', absolute: false).'?tab=avatar');
        $response->assertSessionHas('ui.toast', [
            'type' => 'error',
            'title' => __('ui.profile.oauth_link_facebook_taken'),
        ]);
        $this->assertNull($user->fresh()->profile?->facebook_id);
    }

    #[Test]
    public function callback_creates_a_new_user_when_no_match_exists(): void
    {
        Event::fake([Verified::class]);

        $this->mockSocialiteWith($this->fakeFacebookUser(
            id: '999000111',
            email: 'newuser@example.com',
            name: 'New User',
        ));

        $response = $this->get(route('facebook.callback'));

        $response->assertRedirect(route('dashboard', absolute: false));

        $user = User::where('email', 'newuser@example.com')->firstOrFail();
        $this->assertSame('999000111', $user->profile?->facebook_id);
        $this->assertSame('newuser', $user->nickname);
        $this->assertNotNull($user->email_verified_at);
        $this->assertAuthenticatedAs($user);
        Event::assertDispatched(Verified::class);
    }

    #[Test]
    public function callback_persists_timezone_from_browser_cookie_for_new_user(): void
    {
        Event::fake([Verified::class]);

        $this->mockSocialiteWith($this->fakeFacebookUser(
            id: '212121212',
            email: 'timezone-user@example.com',
            name: 'Timezone User',
        ));

        $response = $this
            ->withCookie('browser_timezone', 'Europe/Warsaw')
            ->get(route('facebook.callback'));

        $response->assertRedirect(route('dashboard', absolute: false));

        $user = User::where('email', 'timezone-user@example.com')->firstOrFail();
        $this->assertSame('Europe/Warsaw', $user->profile?->timezone);
    }

    #[Test]
    public function callback_creates_a_new_user_with_suffixed_nickname_on_collision(): void
    {
        Event::fake([Verified::class]);

        // Str::slug strips dots, so 'jane.doe' -> 'janedoe'.
        User::factory()->create(['nickname' => 'janedoe']);
        User::factory()->create(['nickname' => 'janedoe_2']);

        $this->mockSocialiteWith($this->fakeFacebookUser(
            id: '888777666',
            email: 'jane.doe@yahoo.com',
            name: 'Jane Doe',
        ));

        $response = $this->get(route('facebook.callback'));

        $response->assertRedirect(route('dashboard', absolute: false));

        $user = User::where('email', 'jane.doe@yahoo.com')->firstOrFail();
        $this->assertSame('janedoe_3', $user->nickname);
    }

    #[Test]
    public function callback_links_facebook_id_to_existing_user_matched_by_email(): void
    {
        Event::fake([Verified::class]);

        $existing = User::factory()->unverified()->create([
            'email' => 'linked@example.com',
        ]);

        $this->mockSocialiteWith($this->fakeFacebookUser(
            id: '555444333',
            email: 'linked@example.com',
        ));

        $response = $this->get(route('facebook.callback'));

        $response->assertRedirect(route('dashboard', absolute: false));

        $existing->refresh();
        $this->assertSame('555444333', $existing->profile?->facebook_id);
        $this->assertNotNull($existing->email_verified_at);
        $this->assertAuthenticatedAs($existing);
        Event::assertDispatched(Verified::class);
    }

    #[Test]
    public function callback_logs_in_existing_user_matched_by_facebook_id(): void
    {
        $existing = User::factory()->create([
            'email' => 'returning@example.com',
        ]);
        $existing->profile()->update([
            'facebook_id' => '777888999',
        ]);

        $this->mockSocialiteWith($this->fakeFacebookUser(
            id: '777888999',
            email: 'different-current@example.com',
        ));

        $response = $this->get(route('facebook.callback'));

        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs($existing);

        $this->assertSame(1, User::whereHas('profile', fn ($query) => $query->where('facebook_id', '777888999'))->count());
    }

    #[Test]
    public function callback_redirects_to_login_when_facebook_returns_no_email(): void
    {
        $this->mockSocialiteWith($this->fakeFacebookUser(
            id: '111222333',
            email: null,
        ));

        $response = $this->get(route('facebook.callback'));

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('status');
        $this->assertGuest();
        $this->assertSame(0, User::whereHas('profile', fn ($query) => $query->where('facebook_id', '111222333'))->count());
    }

    #[Test]
    public function callback_persists_facebook_provider_data_from_profile_link(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'name' => 'Jane Doe',
                'link' => 'https://www.facebook.com/janedoe',
            ]),
        ]);

        $this->mockSocialiteWith($this->fakeFacebookUser(
            id: 'app-scoped-facebook-id',
            email: 'jane@example.com',
            name: 'Jane Doe',
            link: 'https://www.facebook.com/janedoe',
        ));

        $response = $this->get(route('facebook.callback'));

        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticated();

        $profile = User::query()->where('email', 'jane@example.com')->first()?->profile;
        $this->assertNotNull($profile);
        $this->assertSame('app-scoped-facebook-id', $profile->facebook_id);
        $this->assertSame('https://www.facebook.com/janedoe', $profile->facebook_data['profile_url'] ?? null);
        $this->assertSame('janedoe', $profile->facebook_data['vanity'] ?? null);
        $this->assertSame('https://m.me/janedoe', $profile->facebook_data['messenger_url'] ?? null);
    }

    #[Test]
    public function callback_persists_public_facebook_id_from_graph_profile_link(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'name' => 'Jane Doe',
                'link' => 'https://www.facebook.com/profile.php?id=1234567890',
            ]),
        ]);

        $this->mockSocialiteWith($this->fakeFacebookUser(
            id: 'app-scoped-facebook-id',
            email: 'jane@example.com',
            name: 'Jane Doe',
        ));

        $response = $this->get(route('facebook.callback'));

        $response->assertRedirect(route('dashboard', absolute: false));

        $profile = User::query()->where('email', 'jane@example.com')->first()?->profile;
        $this->assertSame('1234567890', $profile->facebook_data['public_id'] ?? null);
        $this->assertSame(
            'https://www.facebook.com/profile.php?id=1234567890',
            $profile->facebook_data['profile_url'] ?? null,
        );
    }
}
