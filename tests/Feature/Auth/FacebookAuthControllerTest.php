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
use Tests\Feature\Auth\Concerns\TestsOAuthProfileLinkingSessionAuth;
use Tests\Feature\Auth\Concerns\TestsOAuthProviderAuth;
use Tests\Feature\Auth\Concerns\TestsOAuthProviderRegistrationAuth;
use Tests\TestCase;

class FacebookAuthControllerTest extends TestCase
{
    use RefreshDatabase;
    use TestsOAuthProfileLinkingSessionAuth;
    use TestsOAuthProviderAuth;
    use TestsOAuthProviderRegistrationAuth;

    protected function oauthRedirectRoute(): string
    {
        return 'facebook.redirect';
    }

    protected function oauthCallbackRoute(): string
    {
        return 'facebook.callback';
    }

    protected function oauthExternalRedirectUrl(): string
    {
        return 'https://facebook.com/oauth';
    }

    protected function providerIdColumn(): string
    {
        return 'facebook_id';
    }

    protected function providerEmailColumn(): string
    {
        return 'facebook_email';
    }

    protected function avatarSourceWhenLinkingAvatarTab(): AvatarSource
    {
        return AvatarSource::Facebook;
    }

    protected function oauthLinkSuccessTranslationKey(): string
    {
        return 'ui.profile.oauth_link_facebook_success';
    }

    protected function oauthLinkTakenTranslationKey(): string
    {
        return 'ui.profile.oauth_link_facebook_taken';
    }

    protected function makeProviderUser(
        string $id,
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

    protected function assertExtraNewUserOutcomes(User $user, SocialiteUser $providerUser): void
    {
        $this->assertSame('newuser', $user->nickname);
        $this->assertNotNull($user->email_verified_at);
        Event::assertDispatched(Verified::class);
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
        $provider->shouldReceive('redirect')->andReturn(redirect($this->oauthExternalRedirectUrl()));

        Socialite::shouldReceive('driver')->with('facebook')->andReturn($provider);
    }

    #[Test]
    public function callback_links_facebook_without_email_when_provider_id_is_present(): void
    {
        $user = User::factory()->create([
            'email' => 'local@example.com',
        ]);

        $this->mockSocialiteWith($this->makeProviderUser(
            id: 'fb-no-email',
            email: null,
        ));

        $response = $this
            ->actingAs($user)
            ->withSession([
                'socialite.link_user_id' => $user->id,
                'socialite.return_tab' => 'avatar',
            ])
            ->get(route($this->oauthCallbackRoute()));

        $response->assertRedirect(route('profile', absolute: false).'?tab=avatar');
        $this->assertSame('fb-no-email', $this->profileProviderId($user->fresh()->profile));
    }

    #[Test]
    public function callback_persists_timezone_from_browser_cookie_for_new_user(): void
    {
        Event::fake([Verified::class]);

        $this->mockSocialiteWith($this->makeProviderUser(
            id: '212121212',
            email: 'timezone-user@example.com',
            name: 'Timezone User',
        ));

        $response = $this
            ->withCookie('browser_timezone', 'Europe/Warsaw')
            ->get(route($this->oauthCallbackRoute()));

        $response->assertRedirect(route('dashboard', absolute: false));

        $user = User::where('email', 'timezone-user@example.com')->firstOrFail();
        $this->assertSame('Europe/Warsaw', $user->profile?->timezone);
    }

    #[Test]
    public function callback_defaults_timezone_to_europe_warsaw_when_browser_cookie_missing(): void
    {
        Event::fake([Verified::class]);

        $this->mockSocialiteWith($this->makeProviderUser(
            id: '313131313',
            email: 'tz-default-user@example.com',
            name: 'TZ Default User',
        ));

        $response = $this->get(route($this->oauthCallbackRoute()));

        $response->assertRedirect(route('dashboard', absolute: false));

        $user = User::where('email', 'tz-default-user@example.com')->firstOrFail();
        $this->assertSame('Europe/Warsaw', $user->profile?->timezone);
    }

    #[Test]
    public function callback_creates_a_new_user_with_suffixed_nickname_on_collision(): void
    {
        Event::fake([Verified::class]);

        User::factory()->create(['nickname' => 'janedoe']);
        User::factory()->create(['nickname' => 'janedoe_2']);

        $this->mockSocialiteWith($this->makeProviderUser(
            id: '888777666',
            email: 'jane.doe@yahoo.com',
            name: 'Jane Doe',
        ));

        $response = $this->get(route($this->oauthCallbackRoute()));

        $response->assertRedirect(route('dashboard', absolute: false));

        $user = User::where('email', 'jane.doe@yahoo.com')->firstOrFail();
        $this->assertSame('janedoe_3', $user->nickname);
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

        $this->mockSocialiteWith($this->makeProviderUser(
            id: 'app-scoped-facebook-id',
            email: 'jane@example.com',
            name: 'Jane Doe',
            link: 'https://www.facebook.com/janedoe',
        ));

        $response = $this->get(route($this->oauthCallbackRoute()));

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

        $this->mockSocialiteWith($this->makeProviderUser(
            id: 'app-scoped-facebook-id',
            email: 'jane@example.com',
            name: 'Jane Doe',
        ));

        $response = $this->get(route($this->oauthCallbackRoute()));

        $response->assertRedirect(route('dashboard', absolute: false));

        $profile = User::query()->where('email', 'jane@example.com')->first()?->profile;
        $this->assertSame('1234567890', $profile->facebook_data['public_id'] ?? null);
        $this->assertSame(
            'https://www.facebook.com/profile.php?id=1234567890',
            $profile->facebook_data['profile_url'] ?? null,
        );
    }
}
