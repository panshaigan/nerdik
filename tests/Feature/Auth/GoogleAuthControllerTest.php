<?php

namespace Tests\Feature\Auth;

use App\Enums\AvatarSource;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GoogleAuthControllerTest extends TestCase
{
    use RefreshDatabase;

    private function fakeGoogleUser(string $id = 'google-123', ?string $email = 'jane@example.com', string $name = 'Jane Doe'): SocialiteUser
    {
        $googleUser = new SocialiteUser;
        $googleUser->id = $id;
        $googleUser->name = $name;
        $googleUser->email = $email;
        $googleUser->token = 'fake-token';
        $googleUser->user = ['verified_email' => true];

        return $googleUser;
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
        $provider->shouldReceive('redirect')->andReturn(redirect('https://accounts.google.com/oauth'));

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }

    #[Test]
    public function authenticated_user_can_start_google_redirect_for_contact_linking(): void
    {
        $user = User::factory()->create();

        $this->mockSocialiteRedirect();

        $response = $this->actingAs($user)->get(route('google.redirect', ['return_tab' => 'contact']));

        $response->assertRedirect('https://accounts.google.com/oauth');
        $response->assertCookie('oauth_link_user_id', (string) $user->id);
        $this->assertSame($user->id, session('socialite.link_user_id'));
        $this->assertSame('contact', session('socialite.return_tab'));
    }

    #[Test]
    public function callback_links_google_from_contact_tab_without_changing_avatar_source(): void
    {
        $user = User::factory()->create([
            'email' => 'contact-linker@example.com',
        ]);
        $user->profile()->update([
            'avatar_source' => AvatarSource::Generated,
        ]);

        $this->mockSocialiteWith($this->fakeGoogleUser(
            id: 'google-contact-link',
            email: 'contact-linker@example.com',
        ));

        $response = $this
            ->actingAs($user)
            ->withSession([
                'socialite.link_user_id' => $user->id,
                'socialite.return_tab' => 'contact',
            ])
            ->get(route('google.callback'));

        $response->assertRedirect(route('profile', absolute: false).'?tab=contact');
        $response->assertSessionHas('ui.toast', [
            'type' => 'success',
            'title' => __('ui.profile.oauth_link_google_success'),
        ]);
        $this->assertAuthenticatedAs($user);
        $this->assertSame('google-contact-link', $user->fresh()->profile?->google_id);
        $this->assertSame('contact-linker@example.com', $user->fresh()->profile?->google_email);
        $this->assertSame(AvatarSource::Generated, $user->fresh()->profile?->avatar_source);
    }

    #[Test]
    public function authenticated_user_can_start_google_redirect_for_avatar_linking(): void
    {
        $user = User::factory()->create();

        $this->mockSocialiteRedirect();

        $response = $this->actingAs($user)->get(route('google.redirect', ['return_tab' => 'avatar']));

        $response->assertRedirect('https://accounts.google.com/oauth');
        $response->assertCookie('oauth_link_user_id', (string) $user->id);
        $this->assertSame($user->id, session('socialite.link_user_id'));
        $this->assertSame('avatar', session('socialite.return_tab'));
    }

    #[Test]
    public function callback_links_google_to_authenticated_user_when_linking_from_profile(): void
    {
        $user = User::factory()->create([
            'email' => 'linker@example.com',
        ]);

        $this->mockSocialiteWith($this->fakeGoogleUser(
            id: 'google-link-1',
            email: 'linker@example.com',
        ));

        $response = $this
            ->actingAs($user)
            ->withSession([
                'socialite.link_user_id' => $user->id,
                'socialite.return_tab' => 'avatar',
            ])
            ->get(route('google.callback'));

        $response->assertRedirect(route('profile', absolute: false).'?tab=avatar');
        $this->assertAuthenticatedAs($user);
        $this->assertSame('google-link-1', $user->fresh()->profile?->google_id);
        $this->assertSame(AvatarSource::Google, $user->fresh()->profile?->avatar_source);
    }

    #[Test]
    public function callback_links_google_when_email_differs_from_account_during_profile_linking(): void
    {
        $user = User::factory()->create([
            'email' => 'local@example.com',
        ]);

        $this->mockSocialiteWith($this->fakeGoogleUser(
            id: 'google-mismatch',
            email: 'other@example.com',
        ));

        $response = $this
            ->actingAs($user)
            ->withSession([
                'socialite.link_user_id' => $user->id,
                'socialite.return_tab' => 'avatar',
            ])
            ->get(route('google.callback'));

        $response->assertRedirect(route('profile', absolute: false).'?tab=avatar');
        $response->assertSessionHas('ui.toast', [
            'type' => 'success',
            'title' => __('ui.profile.oauth_link_google_success'),
        ]);
        $this->assertAuthenticatedAs($user);
        $this->assertSame('google-mismatch', $user->fresh()->profile?->google_id);
        $this->assertSame('other@example.com', $user->fresh()->profile?->google_email);
        $this->assertSame(AvatarSource::Google, $user->fresh()->profile?->avatar_source);
    }

    #[Test]
    public function callback_does_not_store_google_email_when_provider_marks_it_unverified(): void
    {
        $user = User::factory()->create([
            'email' => 'local@example.com',
        ]);

        $googleUser = $this->fakeGoogleUser(
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
            ->get(route('google.callback'));

        $response->assertRedirect(route('profile', absolute: false).'?tab=contact');
        $this->assertSame('google-unverified', $user->fresh()->profile?->google_id);
        $this->assertNull($user->fresh()->profile?->google_email);
    }

    #[Test]
    public function callback_rejects_google_cookie_without_session_link_intent(): void
    {
        $user = User::factory()->create([
            'email' => 'local@example.com',
        ]);

        $this->mockSocialiteWith($this->fakeGoogleUser(
            id: 'google-cookie-link',
            email: 'other@example.com',
        ));

        $response = $this
            ->withCookie('oauth_link_user_id', (string) $user->id)
            ->get(route('google.callback'));

        $response->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertNull($user->fresh()->profile?->google_id);
    }

    #[Test]
    public function callback_rejects_google_linking_when_id_belongs_to_another_user(): void
    {
        $other = User::factory()->create();
        $other->profile()->update(['google_id' => 'taken-google-id']);

        $user = User::factory()->create([
            'email' => 'linker@example.com',
        ]);

        $this->mockSocialiteWith($this->fakeGoogleUser(
            id: 'taken-google-id',
            email: 'linker@example.com',
        ));

        $response = $this
            ->actingAs($user)
            ->withSession([
                'socialite.link_user_id' => $user->id,
                'socialite.return_tab' => 'avatar',
            ])
            ->get(route('google.callback'));

        $response->assertRedirect(route('profile', absolute: false).'?tab=avatar');
        $response->assertSessionHas('ui.toast', [
            'type' => 'error',
            'title' => __('ui.profile.oauth_link_google_taken'),
        ]);
        $this->assertNull($user->fresh()->profile?->google_id);
    }

    #[Test]
    public function callback_creates_a_new_user_when_no_match_exists(): void
    {
        Event::fake([Verified::class]);

        $this->mockSocialiteWith($this->fakeGoogleUser(
            id: 'google-new-1',
            email: 'newuser@example.com',
            name: 'New User',
        ));

        $response = $this->get(route('google.callback'));

        $response->assertRedirect(route('dashboard', absolute: false));

        $user = User::where('email', 'newuser@example.com')->firstOrFail();
        $this->assertSame('google-new-1', $user->profile?->google_id);
        $this->assertAuthenticatedAs($user);
    }

    #[Test]
    public function callback_redirects_to_login_when_oauth_state_is_invalid(): void
    {
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->andThrow(new InvalidStateException);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $response = $this->get(route('google.callback'));

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('status', __('ui.auth.oauth_state_invalid'));
        $this->assertGuest();
    }

    #[Test]
    public function callback_redirects_to_login_when_provider_returns_error(): void
    {
        $response = $this->get(route('google.callback', [
            'error' => 'access_denied',
        ]));

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('status', __('ui.auth.oauth_denied'));
        $this->assertGuest();
    }

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
        $providerUser = $this->fakeGoogleUser();
        $providerUser->user = $verified === null ? [] : ['verified_email' => $verified];
        $this->mockSocialiteWith($providerUser);

        $this->get(route('google.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', __('ui.auth.oauth_verified_email_required'));

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => $providerUser->getEmail()]);
    }

    #[Test]
    public function callback_does_not_link_existing_email_even_when_verified(): void
    {
        $existing = User::factory()->create(['email' => 'jane@example.com']);
        $this->mockSocialiteWith($this->fakeGoogleUser());

        $this->get(route('google.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', __('ui.auth.oauth_existing_email'));

        $this->assertGuest();
        $this->assertNull($existing->fresh()->profile?->google_id);
    }

    #[Test]
    public function existing_provider_id_can_login_without_verified_provider_email(): void
    {
        $existing = User::factory()->create();
        $providerUser = $this->fakeGoogleUser();
        $providerUser->user = ['verified_email' => false];
        $existing->profile()->update(['google_id' => $providerUser->getId()]);
        $this->mockSocialiteWith($providerUser);

        $this->get(route('google.callback'))->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($existing);
        $this->assertNull($existing->fresh()->profile?->google_email);
    }

    #[Test]
    public function session_link_intent_cannot_link_after_logout_or_to_a_different_user(): void
    {
        $target = User::factory()->create();
        $this->mockSocialiteWith($this->fakeGoogleUser());

        $this->withSession(['socialite.link_user_id' => $target->id])
            ->get(route('google.callback'))->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertNull($target->fresh()->profile?->google_id);

        $other = User::factory()->create();
        $this->actingAs($other)
            ->withSession(['socialite.link_user_id' => $target->id])
            ->get(route('google.callback'))->assertRedirect(route('login'));

        $this->assertAuthenticatedAs($other);
        $this->assertNull($target->fresh()->profile?->google_id);
        $this->assertNull($other->fresh()->profile?->google_id);
    }

    #[Test]
    public function unverified_provider_email_cannot_take_over_an_existing_account(): void
    {
        $existing = User::factory()->unverified()->create(['email' => 'jane@example.com']);
        $providerUser = $this->fakeGoogleUser();
        $providerUser->user = ['verified_email' => false];
        $this->mockSocialiteWith($providerUser);

        $this->get(route('google.callback'))->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertNull($existing->fresh()->profile?->google_id);
        $this->assertFalse($existing->fresh()->hasVerifiedEmail());
    }
}
