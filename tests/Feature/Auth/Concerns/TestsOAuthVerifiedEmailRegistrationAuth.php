<?php

namespace Tests\Feature\Auth\Concerns;

use App\Models\User;
use Laravel\Socialite\Two\User as SocialiteUser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * Verified-email registration gate and session-link security (Google + Discord).
 *
 * Requires {@see TestsOAuthProviderAuth} on the same test class for profile helpers.
 */
trait TestsOAuthVerifiedEmailRegistrationAuth
{
    abstract protected function oauthCallbackRoute(): string;

    abstract protected function mockSocialiteWith(SocialiteUser $providerUser): void;

    abstract protected function makeProviderUser(
        string $id,
        ?string $email = 'jane@example.com',
        string $name = 'Jane Doe',
    ): SocialiteUser;

    abstract protected function applyUntrustedEmailVerification(SocialiteUser $providerUser, bool|string|null $verified): void;

    abstract protected function markProviderEmailUnverified(SocialiteUser $providerUser): void;

    /**
     * @return array<string, array{bool|string|null}>
     */
    public static function untrustedEmailVerification(): array
    {
        return [
            'unverified' => [false],
            'missing' => [null],
            'non-boolean' => ['false'],
        ];
    }

    #[Test]
    #[DataProvider('untrustedEmailVerification')]
    public function callback_rejects_unverified_or_missing_email_verification_for_registration(bool|string|null $verified): void
    {
        $providerUser = $this->defaultProviderUser();
        $this->applyUntrustedEmailVerification($providerUser, $verified);
        $this->mockSocialiteWith($providerUser);

        $this->get(route($this->oauthCallbackRoute()))
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', __('ui.auth.oauth_verified_email_required'));

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => $providerUser->getEmail()]);
    }

    #[Test]
    public function callback_does_not_link_existing_email_even_when_verified(): void
    {
        $existing = User::factory()->create(['email' => 'jane@example.com']);
        $this->mockSocialiteWith($this->defaultProviderUser());

        $this->get(route($this->oauthCallbackRoute()))
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', __('ui.auth.oauth_existing_email'));

        $this->assertGuest();
        $this->assertNull($this->profileProviderId($existing->fresh()->profile));
    }

    #[Test]
    public function existing_provider_id_can_login_without_verified_provider_email(): void
    {
        $existing = User::factory()->create();
        $providerUser = $this->defaultProviderUser();
        $this->markProviderEmailUnverified($providerUser);
        $existing->profile()->update([$this->providerIdColumn() => $providerUser->getId()]);
        $this->mockSocialiteWith($providerUser);

        $this->get(route($this->oauthCallbackRoute()))->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($existing);
        $this->assertNull($this->profileProviderEmail($existing->fresh()->profile));
    }

    #[Test]
    public function session_link_intent_cannot_link_after_logout_or_to_a_different_user(): void
    {
        $target = User::factory()->create();
        $this->mockSocialiteWith($this->defaultProviderUser());

        $this->withSession(['socialite.link_user_id' => $target->id])
            ->get(route($this->oauthCallbackRoute()))->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertNull($this->profileProviderId($target->fresh()->profile));

        $other = User::factory()->create();
        $this->actingAs($other)
            ->withSession(['socialite.link_user_id' => $target->id])
            ->get(route($this->oauthCallbackRoute()))->assertRedirect(route('login'));

        $this->assertAuthenticatedAs($other);
        $this->assertNull($this->profileProviderId($target->fresh()->profile));
        $this->assertNull($this->profileProviderId($other->fresh()->profile));
    }

    #[Test]
    public function unverified_provider_email_cannot_take_over_an_existing_account(): void
    {
        $existing = User::factory()->unverified()->create(['email' => 'jane@example.com']);
        $providerUser = $this->defaultProviderUser();
        $this->markProviderEmailUnverified($providerUser);
        $this->mockSocialiteWith($providerUser);

        $this->get(route($this->oauthCallbackRoute()))->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertNull($this->profileProviderId($existing->fresh()->profile));
        $this->assertFalse($existing->fresh()->hasVerifiedEmail());
    }
}
