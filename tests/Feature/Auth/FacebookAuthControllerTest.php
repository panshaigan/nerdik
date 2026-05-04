<?php

namespace Tests\Feature\Auth;

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

class FacebookAuthControllerTest extends TestCase
{
    use RefreshDatabase;

    private function fakeFacebookUser(string $id = '123456789', ?string $email = 'jane@example.com', string $name = 'Jane Doe'): SocialiteUser
    {
        $facebookUser = new SocialiteUser;
        $facebookUser->id = $id;
        $facebookUser->name = $name;
        $facebookUser->email = $email;
        $facebookUser->token = 'fake-token';

        return $facebookUser;
    }

    private function mockSocialiteWith(SocialiteUser $facebookUser): void
    {
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('scopes')->with(['email'])->andReturnSelf();
        $provider->shouldReceive('user')->andReturn($facebookUser);

        Socialite::shouldReceive('driver')->with('facebook')->andReturn($provider);
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
}
