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

class DiscordAuthControllerTest extends TestCase
{
    use RefreshDatabase;

    private function fakeDiscordUser(
        string $id = '123456789012345678',
        ?string $email = 'jane@example.com',
        string $name = 'Jane Doe',
        ?string $nickname = 'janedoe',
        ?string $avatar = 'https://cdn.discordapp.com/avatars/123456789012345678/abc.webp',
    ): SocialiteUser {
        $discordUser = new SocialiteUser;
        $discordUser->id = $id;
        $discordUser->name = $name;
        $discordUser->email = $email;
        $discordUser->nickname = $nickname;
        $discordUser->avatar = $avatar;
        $discordUser->token = 'fake-token';

        return $discordUser;
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
        $provider->shouldReceive('redirect')->andReturn(redirect('https://discord.com/oauth'));

        Socialite::shouldReceive('driver')->with('discord')->andReturn($provider);
    }

    #[Test]
    public function authenticated_user_can_start_discord_redirect_for_contact_linking(): void
    {
        $user = User::factory()->create();

        $this->mockSocialiteRedirect();

        $response = $this->actingAs($user)->get(route('discord.redirect', ['return_tab' => 'contact']));

        $response->assertRedirect('https://discord.com/oauth');
        $response->assertCookie('oauth_link_user_id', (string) $user->id);
        $this->assertSame($user->id, session('socialite.link_user_id'));
        $this->assertSame('contact', session('socialite.return_tab'));
    }

    #[Test]
    public function callback_links_discord_from_contact_tab_without_changing_avatar_source(): void
    {
        $user = User::factory()->create([
            'email' => 'contact-linker@example.com',
        ]);
        $user->profile()->update([
            'avatar_source' => AvatarSource::Generated,
        ]);

        $this->mockSocialiteWith($this->fakeDiscordUser(
            id: 'dc-contact-link',
            email: 'contact-linker@example.com',
        ));

        $response = $this
            ->withSession([
                'socialite.link_user_id' => $user->id,
                'socialite.return_tab' => 'contact',
            ])
            ->get(route('discord.callback'));

        $response->assertRedirect(route('profile', absolute: false).'?tab=contact');
        $response->assertSessionHas('ui.toast', [
            'type' => 'success',
            'title' => __('ui.profile.oauth_link_discord_success'),
        ]);
        $this->assertAuthenticatedAs($user);
        $this->assertSame('dc-contact-link', $user->fresh()->profile?->discord_id);
        $this->assertSame('janedoe', $user->fresh()->profile?->discord_handle);
        $this->assertSame(AvatarSource::Generated, $user->fresh()->profile?->avatar_source);
    }

    #[Test]
    public function authenticated_user_can_start_discord_redirect_for_avatar_linking(): void
    {
        $user = User::factory()->create();

        $this->mockSocialiteRedirect();

        $response = $this->actingAs($user)->get(route('discord.redirect', ['return_tab' => 'avatar']));

        $response->assertRedirect('https://discord.com/oauth');
        $response->assertCookie('oauth_link_user_id', (string) $user->id);
        $this->assertSame($user->id, session('socialite.link_user_id'));
        $this->assertSame('avatar', session('socialite.return_tab'));
    }

    #[Test]
    public function callback_links_discord_to_authenticated_user_when_linking_from_profile(): void
    {
        $user = User::factory()->create([
            'email' => 'linker@example.com',
        ]);

        $this->mockSocialiteWith($this->fakeDiscordUser(
            id: 'dc-link-1',
            email: 'linker@example.com',
        ));

        $response = $this
            ->withSession([
                'socialite.link_user_id' => $user->id,
                'socialite.return_tab' => 'avatar',
            ])
            ->get(route('discord.callback'));

        $response->assertRedirect(route('profile', absolute: false).'?tab=avatar');
        $this->assertAuthenticatedAs($user);
        $this->assertSame('dc-link-1', $user->fresh()->profile?->discord_id);
        $this->assertSame(AvatarSource::Discord, $user->fresh()->profile?->avatar_source);
    }

    #[Test]
    public function callback_rejects_discord_linking_when_id_belongs_to_another_user(): void
    {
        $other = User::factory()->create();
        $other->profile()->update(['discord_id' => 'taken-dc-id']);

        $user = User::factory()->create([
            'email' => 'linker@example.com',
        ]);

        $this->mockSocialiteWith($this->fakeDiscordUser(
            id: 'taken-dc-id',
            email: 'linker@example.com',
        ));

        $response = $this
            ->withSession([
                'socialite.link_user_id' => $user->id,
                'socialite.return_tab' => 'avatar',
            ])
            ->get(route('discord.callback'));

        $response->assertRedirect(route('profile', absolute: false).'?tab=avatar');
        $response->assertSessionHas('ui.toast', [
            'type' => 'error',
            'title' => __('ui.profile.oauth_link_discord_taken'),
        ]);
        $this->assertNull($user->fresh()->profile?->discord_id);
    }

    #[Test]
    public function callback_creates_a_new_user_when_no_match_exists(): void
    {
        Event::fake([Verified::class]);

        $this->mockSocialiteWith($this->fakeDiscordUser(
            id: '999000111222333444',
            email: 'newuser@example.com',
            name: 'New User',
        ));

        $response = $this->get(route('discord.callback'));

        $response->assertRedirect(route('dashboard', absolute: false));

        $user = User::where('email', 'newuser@example.com')->firstOrFail();
        $this->assertSame('999000111222333444', $user->profile?->discord_id);
        $this->assertSame('newuser', $user->nickname);
        $this->assertNotNull($user->email_verified_at);
        $this->assertAuthenticatedAs($user);
        Event::assertDispatched(Verified::class);
    }

    #[Test]
    public function callback_links_discord_id_to_existing_user_matched_by_email(): void
    {
        Event::fake([Verified::class]);

        $existing = User::factory()->unverified()->create([
            'email' => 'linked@example.com',
        ]);

        $this->mockSocialiteWith($this->fakeDiscordUser(
            id: '555444333222111000',
            email: 'linked@example.com',
        ));

        $response = $this->get(route('discord.callback'));

        $response->assertRedirect(route('dashboard', absolute: false));

        $existing->refresh();
        $this->assertSame('555444333222111000', $existing->profile?->discord_id);
        $this->assertNotNull($existing->email_verified_at);
        $this->assertAuthenticatedAs($existing);
        Event::assertDispatched(Verified::class);
    }

    #[Test]
    public function callback_logs_in_existing_user_matched_by_discord_id(): void
    {
        $existing = User::factory()->create([
            'email' => 'returning@example.com',
        ]);
        $existing->profile()->update([
            'discord_id' => '777888999000111222',
        ]);

        $this->mockSocialiteWith($this->fakeDiscordUser(
            id: '777888999000111222',
            email: 'different-current@example.com',
        ));

        $response = $this->get(route('discord.callback'));

        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs($existing);

        $this->assertSame(1, User::whereHas('profile', fn ($query) => $query->where('discord_id', '777888999000111222'))->count());
    }

    #[Test]
    public function callback_redirects_to_login_when_discord_returns_no_email(): void
    {
        $this->mockSocialiteWith($this->fakeDiscordUser(
            id: '111222333444555666',
            email: null,
        ));

        $response = $this->get(route('discord.callback'));

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('status');
        $this->assertGuest();
        $this->assertSame(0, User::whereHas('profile', fn ($query) => $query->where('discord_id', '111222333444555666'))->count());
    }
}
