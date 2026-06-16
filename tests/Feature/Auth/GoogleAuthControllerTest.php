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
    public function callback_links_google_using_cookie_when_session_link_user_id_is_missing(): void
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

        $response->assertRedirect(route('profile', absolute: false).'?tab=avatar');
        $this->assertAuthenticatedAs($user);
        $this->assertSame('google-cookie-link', $user->fresh()->profile?->google_id);
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
}
