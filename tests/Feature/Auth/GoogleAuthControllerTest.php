<?php

namespace Tests\Feature\Auth;

use App\Enums\AvatarSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Auth\Concerns\TestsOAuthProfileLinkingSessionAuth;
use Tests\Feature\Auth\Concerns\TestsOAuthProviderAuth;
use Tests\Feature\Auth\Concerns\TestsOAuthVerifiedEmailRegistrationAuth;
use Tests\TestCase;

class GoogleAuthControllerTest extends TestCase
{
    use RefreshDatabase;
    use TestsOAuthProfileLinkingSessionAuth;
    use TestsOAuthProviderAuth;
    use TestsOAuthVerifiedEmailRegistrationAuth;

    protected function oauthRedirectRoute(): string
    {
        return 'google.redirect';
    }

    protected function oauthCallbackRoute(): string
    {
        return 'google.callback';
    }

    protected function oauthExternalRedirectUrl(): string
    {
        return 'https://accounts.google.com/oauth';
    }

    protected function providerIdColumn(): string
    {
        return 'google_id';
    }

    protected function providerEmailColumn(): string
    {
        return 'google_email';
    }

    protected function avatarSourceWhenLinkingAvatarTab(): AvatarSource
    {
        return AvatarSource::Google;
    }

    protected function oauthLinkSuccessTranslationKey(): string
    {
        return 'ui.profile.oauth_link_google_success';
    }

    protected function oauthLinkTakenTranslationKey(): string
    {
        return 'ui.profile.oauth_link_google_taken';
    }

    protected function makeProviderUser(
        string $id,
        ?string $email = 'jane@example.com',
        string $name = 'Jane Doe',
    ): SocialiteUser {
        $googleUser = new SocialiteUser;
        $googleUser->id = $id;
        $googleUser->name = $name;
        $googleUser->email = $email;
        $googleUser->token = 'fake-token';
        $googleUser->user = ['verified_email' => true];

        return $googleUser;
    }

    protected function applyUntrustedEmailVerification(SocialiteUser $providerUser, bool|string|null $verified): void
    {
        $providerUser->user = $verified === null ? [] : ['verified_email' => $verified];
    }

    protected function markProviderEmailUnverified(SocialiteUser $providerUser): void
    {
        $providerUser->user = ['verified_email' => false];
    }

    private function mockSocialiteWith(SocialiteUser $googleUser): void
    {
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('scopes')->with(['openid', 'profile', 'email'])->andReturnSelf();
        $provider->shouldReceive('user')->andReturn($googleUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }

    private function mockSocialiteRedirect(): void
    {
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('scopes')->with(['openid', 'profile', 'email'])->andReturnSelf();
        $provider->shouldReceive('redirect')->andReturn(redirect($this->oauthExternalRedirectUrl()));

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }

    #[Test]
    public function callback_does_not_store_google_email_when_provider_marks_it_unverified(): void
    {
        $user = User::factory()->create([
            'email' => 'local@example.com',
        ]);

        $googleUser = $this->makeProviderUser(
            id: 'google-unverified',
            email: 'unverified@example.com',
        );
        $googleUser->user = ['verified_email' => false];

        $this->mockSocialiteWith($googleUser);

        $response = $this
            ->actingAs($user)
            ->withSession([
                'socialite.link_user_id' => $user->id,
                'socialite.return_tab' => 'contact',
            ])
            ->get(route($this->oauthCallbackRoute()));

        $response->assertRedirect(route('profile', absolute: false).'?tab=contact');
        $this->assertSame('google-unverified', $this->profileProviderId($user->fresh()->profile));
        $this->assertNull($this->profileProviderEmail($user->fresh()->profile));
    }

    #[Test]
    public function callback_redirects_to_login_when_oauth_state_is_invalid(): void
    {
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->andThrow(new InvalidStateException);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $response = $this->get(route($this->oauthCallbackRoute()));

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('status', __('ui.auth.oauth_state_invalid'));
        $this->assertGuest();
    }

    #[Test]
    public function callback_redirects_to_login_when_provider_returns_error(): void
    {
        $response = $this->get(route($this->oauthCallbackRoute(), [
            'error' => 'access_denied',
        ]));

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('status', __('ui.auth.oauth_denied'));
        $this->assertGuest();
    }
}
