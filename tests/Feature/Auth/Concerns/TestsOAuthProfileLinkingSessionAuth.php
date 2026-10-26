<?php

namespace Tests\Feature\Auth\Concerns;

use App\Enums\AvatarSource;
use App\Models\User;
use Laravel\Socialite\Two\User as SocialiteUser;
use PHPUnit\Framework\Attributes\Test;

/**
 * Email-mismatch profile linking and cookie-without-session rejection (Google + Facebook).
 *
 * Requires {@see TestsOAuthProviderAuth} on the same test class for profile helpers.
 */
trait TestsOAuthProfileLinkingSessionAuth
{
    abstract protected function oauthCallbackRoute(): string;

    abstract protected function avatarSourceWhenLinkingAvatarTab(): AvatarSource;

    abstract protected function oauthLinkSuccessTranslationKey(): string;

    abstract protected function mockSocialiteWith(SocialiteUser $providerUser): void;

    abstract protected function makeProviderUser(
        string $id,
        ?string $email = 'jane@example.com',
        string $name = 'Jane Doe',
    ): SocialiteUser;

    #[Test]
    public function callback_links_when_email_differs_from_account_during_profile_linking(): void
    {
        $user = User::factory()->create([
            'email' => 'local@example.com',
        ]);

        $providerId = 'email-mismatch-'.str_replace('_', '-', $this->providerIdColumn());

        $this->mockSocialiteWith($this->makeProviderUser(
            id: $providerId,
            email: 'other@example.com',
        ));

        $response = $this
            ->actingAs($user)
            ->withSession([
                'socialite.link_user_id' => $user->id,
                'socialite.return_tab' => 'avatar',
            ])
            ->get(route($this->oauthCallbackRoute()));

        $response->assertRedirect(route('profile', absolute: false).'?tab=avatar');
        $response->assertSessionHas('ui.toast', [
            'type' => 'success',
            'title' => __($this->oauthLinkSuccessTranslationKey()),
        ]);
        $this->assertAuthenticatedAs($user);
        $this->assertSame($providerId, $this->profileProviderId($user->fresh()->profile));
        $this->assertSame('other@example.com', $this->profileProviderEmail($user->fresh()->profile));
        $this->assertSame($this->avatarSourceWhenLinkingAvatarTab(), $user->fresh()->profile?->avatar_source);
    }

    #[Test]
    public function callback_rejects_oauth_cookie_without_session_link_intent(): void
    {
        $user = User::factory()->create([
            'email' => 'local@example.com',
        ]);

        $this->mockSocialiteWith($this->makeProviderUser(
            id: 'cookie-link-'.str_replace('_', '-', $this->providerIdColumn()),
            email: 'other@example.com',
        ));

        $response = $this
            ->withCookie('oauth_link_user_id', (string) $user->id)
            ->get(route($this->oauthCallbackRoute()));

        $response->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertNull($this->profileProviderId($user->fresh()->profile));
    }
}
