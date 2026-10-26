<?php

namespace Tests\Feature\Auth;

use App\Enums\AvatarSource;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\Feature\Auth\Concerns\TestsOAuthProviderAuth;
use Tests\Feature\Auth\Concerns\TestsOAuthProviderRegistrationAuth;
use Tests\Feature\Auth\Concerns\TestsOAuthVerifiedEmailRegistrationAuth;
use Tests\TestCase;

class DiscordAuthControllerTest extends TestCase
{
    use RefreshDatabase;
    use TestsOAuthProviderAuth;
    use TestsOAuthProviderRegistrationAuth;
    use TestsOAuthVerifiedEmailRegistrationAuth;

    protected function oauthRedirectRoute(): string
    {
        return 'discord.redirect';
    }

    protected function oauthCallbackRoute(): string
    {
        return 'discord.callback';
    }

    protected function oauthExternalRedirectUrl(): string
    {
        return 'https://discord.com/oauth';
    }

    protected function providerIdColumn(): string
    {
        return 'discord_id';
    }

    protected function providerEmailColumn(): string
    {
        return 'discord_email';
    }

    protected function avatarSourceWhenLinkingAvatarTab(): AvatarSource
    {
        return AvatarSource::Discord;
    }

    protected function oauthLinkSuccessTranslationKey(): string
    {
        return 'ui.profile.oauth_link_discord_success';
    }

    protected function oauthLinkTakenTranslationKey(): string
    {
        return 'ui.profile.oauth_link_discord_taken';
    }

    protected function makeProviderUser(
        string $id,
        ?string $email = 'jane@example.com',
        string $name = 'Jane Doe',
    ): SocialiteUser {
        $discordUser = new SocialiteUser;
        $discordUser->id = $id;
        $discordUser->name = $name;
        $discordUser->email = $email;
        $discordUser->nickname = 'janedoe';
        $discordUser->avatar = 'https://cdn.discordapp.com/avatars/123456789012345678/abc.webp';
        $discordUser->token = 'fake-token';
        $discordUser->user = ['verified' => true];

        return $discordUser;
    }

    protected function applyUntrustedEmailVerification(SocialiteUser $providerUser, bool|string|null $verified): void
    {
        $providerUser->user = $verified === null ? [] : ['verified' => $verified];
    }

    protected function markProviderEmailUnverified(SocialiteUser $providerUser): void
    {
        $providerUser->user = ['verified' => false];
    }

    protected function assertExtraContactTabLinkOutcomes(User $user): void
    {
        $this->assertSame('janedoe', $user->profile?->discord_handle);
    }

    protected function assertExtraNewUserOutcomes(User $user, SocialiteUser $providerUser): void
    {
        $this->assertSame('newuser', $user->nickname);
        $this->assertNotNull($user->email_verified_at);
        Event::assertDispatched(Verified::class);
    }

    private function mockSocialiteWith(SocialiteUser $discordUser): void
    {
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('scopes')->with(['identify', 'email'])->andReturnSelf();
        $provider->shouldReceive('user')->andReturn($discordUser);

        Socialite::shouldReceive('driver')->with('discord')->andReturn($provider);
    }

    private function mockSocialiteRedirect(): void
    {
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('scopes')->with(['identify', 'email'])->andReturnSelf();
        $provider->shouldReceive('redirect')->andReturn(redirect($this->oauthExternalRedirectUrl()));

        Socialite::shouldReceive('driver')->with('discord')->andReturn($provider);
    }
}
