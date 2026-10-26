<?php

namespace Tests\Feature\Auth\Concerns;

use App\Enums\AvatarSource;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Event;
use Laravel\Socialite\Two\User as SocialiteUser;
use PHPUnit\Framework\Attributes\Test;

/**
 * Shared OAuth controller feature tests. Provider test classes supply Socialite mocks
 * and profile column / copy hooks via abstract methods.
 */
trait TestsOAuthProviderAuth
{
    abstract protected function oauthRedirectRoute(): string;

    abstract protected function oauthCallbackRoute(): string;

    abstract protected function oauthExternalRedirectUrl(): string;

    abstract protected function providerIdColumn(): string;

    abstract protected function providerEmailColumn(): string;

    abstract protected function avatarSourceWhenLinkingAvatarTab(): AvatarSource;

    abstract protected function oauthLinkSuccessTranslationKey(): string;

    abstract protected function oauthLinkTakenTranslationKey(): string;

    abstract protected function mockSocialiteWith(SocialiteUser $providerUser): void;

    abstract protected function mockSocialiteRedirect(): void;

    abstract protected function makeProviderUser(
        string $id,
        ?string $email = 'jane@example.com',
        string $name = 'Jane Doe',
    ): SocialiteUser;

    protected function defaultProviderUser(): SocialiteUser
    {
        return $this->makeProviderUser(
            id: 'provider-default-id',
            email: 'jane@example.com',
            name: 'Jane Doe',
        );
    }

    protected function profileProviderId(?object $profile): ?string
    {
        if ($profile === null) {
            return null;
        }

        $column = $this->providerIdColumn();

        return $profile->{$column};
    }

    protected function profileProviderEmail(?object $profile): ?string
    {
        if ($profile === null) {
            return null;
        }

        $column = $this->providerEmailColumn();

        return $profile->{$column};
    }

    protected function assertExtraContactTabLinkOutcomes(User $user): void
    {
        //
    }

    protected function assertExtraNewUserOutcomes(User $user, SocialiteUser $providerUser): void
    {
        //
    }

    #[Test]
    public function authenticated_user_can_start_oauth_redirect_for_contact_linking(): void
    {
        $user = User::factory()->create();

        $this->mockSocialiteRedirect();

        $response = $this->actingAs($user)->get(route($this->oauthRedirectRoute(), ['return_tab' => 'contact']));

        $response->assertRedirect($this->oauthExternalRedirectUrl());
        $response->assertCookie('oauth_link_user_id', (string) $user->id);
        $this->assertSame($user->id, session('socialite.link_user_id'));
        $this->assertSame('contact', session('socialite.return_tab'));
    }

    #[Test]
    public function callback_links_from_contact_tab_without_changing_avatar_source(): void
    {
        $user = User::factory()->create([
            'email' => 'contact-linker@example.com',
        ]);
        $user->profile()->update([
            'avatar_source' => AvatarSource::Generated,
        ]);

        $providerId = 'contact-link-'.str_replace('_', '-', $this->providerIdColumn());

        $this->mockSocialiteWith($this->makeProviderUser(
            id: $providerId,
            email: 'contact-linker@example.com',
        ));

        $response = $this
            ->actingAs($user)
            ->withSession([
                'socialite.link_user_id' => $user->id,
                'socialite.return_tab' => 'contact',
            ])
            ->get(route($this->oauthCallbackRoute()));

        $response->assertRedirect(route('profile', absolute: false).'?tab=contact');
        $response->assertSessionHas('ui.toast', [
            'type' => 'success',
            'title' => __($this->oauthLinkSuccessTranslationKey()),
        ]);
        $this->assertAuthenticatedAs($user);
        $this->assertSame($providerId, $this->profileProviderId($user->fresh()->profile));
        $this->assertSame(AvatarSource::Generated, $user->fresh()->profile?->avatar_source);
        $this->assertExtraContactTabLinkOutcomes($user->fresh());
    }

    #[Test]
    public function authenticated_user_can_start_oauth_redirect_for_avatar_linking(): void
    {
        $user = User::factory()->create();

        $this->mockSocialiteRedirect();

        $response = $this->actingAs($user)->get(route($this->oauthRedirectRoute(), ['return_tab' => 'avatar']));

        $response->assertRedirect($this->oauthExternalRedirectUrl());
        $response->assertCookie('oauth_link_user_id', (string) $user->id);
        $this->assertSame($user->id, session('socialite.link_user_id'));
        $this->assertSame('avatar', session('socialite.return_tab'));
    }

    #[Test]
    public function callback_links_to_authenticated_user_when_linking_from_profile(): void
    {
        $user = User::factory()->create([
            'email' => 'linker@example.com',
        ]);

        $providerId = 'profile-link-'.str_replace('_', '-', $this->providerIdColumn());

        $this->mockSocialiteWith($this->makeProviderUser(
            id: $providerId,
            email: 'linker@example.com',
        ));

        $response = $this
            ->actingAs($user)
            ->withSession([
                'socialite.link_user_id' => $user->id,
                'socialite.return_tab' => 'avatar',
            ])
            ->get(route($this->oauthCallbackRoute()));

        $response->assertRedirect(route('profile', absolute: false).'?tab=avatar');
        $this->assertAuthenticatedAs($user);
        $this->assertSame($providerId, $this->profileProviderId($user->fresh()->profile));
        $this->assertSame($this->avatarSourceWhenLinkingAvatarTab(), $user->fresh()->profile?->avatar_source);
    }

    #[Test]
    public function callback_rejects_linking_when_id_belongs_to_another_user(): void
    {
        $other = User::factory()->create();
        $other->profile()->update([$this->providerIdColumn() => 'taken-provider-id']);

        $user = User::factory()->create([
            'email' => 'linker@example.com',
        ]);

        $this->mockSocialiteWith($this->makeProviderUser(
            id: 'taken-provider-id',
            email: 'linker@example.com',
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
            'type' => 'error',
            'title' => __($this->oauthLinkTakenTranslationKey()),
        ]);
        $this->assertNull($this->profileProviderId($user->fresh()->profile));
    }

    #[Test]
    public function callback_creates_a_new_user_when_no_match_exists(): void
    {
        Event::fake([Verified::class]);

        $providerId = 'new-user-'.str_replace('_', '-', $this->providerIdColumn());

        $providerUser = $this->makeProviderUser(
            id: $providerId,
            email: 'newuser@example.com',
            name: 'New User',
        );

        $this->mockSocialiteWith($providerUser);

        $response = $this->get(route($this->oauthCallbackRoute()));

        $response->assertRedirect(route('dashboard', absolute: false));

        $user = User::where('email', 'newuser@example.com')->firstOrFail();
        $this->assertSame($providerId, $this->profileProviderId($user->profile));
        $this->assertAuthenticatedAs($user);
        $this->assertExtraNewUserOutcomes($user, $providerUser);
    }
}
