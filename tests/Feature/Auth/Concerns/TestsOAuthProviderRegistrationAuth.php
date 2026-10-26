<?php

namespace Tests\Feature\Auth\Concerns;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Event;
use Laravel\Socialite\Two\User as SocialiteUser;
use PHPUnit\Framework\Attributes\Test;

/**
 * Registration/login flows shared by Facebook and Discord (no verified-email gate on signup).
 *
 * Requires {@see TestsOAuthProviderAuth} on the same test class for profile helpers.
 */
trait TestsOAuthProviderRegistrationAuth
{
    abstract protected function oauthCallbackRoute(): string;

    abstract protected function mockSocialiteWith(SocialiteUser $providerUser): void;

    abstract protected function makeProviderUser(
        string $id,
        ?string $email = 'jane@example.com',
        string $name = 'Jane Doe',
    ): SocialiteUser;

    #[Test]
    public function callback_rejects_automatic_linking_by_email(): void
    {
        Event::fake([Verified::class]);

        $existing = User::factory()->unverified()->create([
            'email' => 'linked@example.com',
        ]);

        $this->mockSocialiteWith($this->makeProviderUser(
            id: '555444333',
            email: 'linked@example.com',
        ));

        $response = $this->get(route($this->oauthCallbackRoute()));

        $response->assertRedirect(route('login'));

        $existing->refresh();
        $this->assertNull($this->profileProviderId($existing->profile));
        $this->assertNull($existing->email_verified_at);
        $this->assertGuest();
        Event::assertNotDispatched(Verified::class);
    }

    #[Test]
    public function callback_logs_in_existing_user_matched_by_provider_id(): void
    {
        $existing = User::factory()->create([
            'email' => 'returning@example.com',
        ]);
        $existing->profile()->update([
            $this->providerIdColumn() => '777888999',
        ]);

        $this->mockSocialiteWith($this->makeProviderUser(
            id: '777888999',
            email: 'different-current@example.com',
        ));

        $response = $this->get(route($this->oauthCallbackRoute()));

        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs($existing);

        $this->assertSame(1, User::whereHas('profile', fn ($query) => $query->where($this->providerIdColumn(), '777888999'))->count());
    }

    #[Test]
    public function callback_redirects_to_login_when_provider_returns_no_email(): void
    {
        $providerId = '111222333';

        $this->mockSocialiteWith($this->makeProviderUser(
            id: $providerId,
            email: null,
        ));

        $response = $this->get(route($this->oauthCallbackRoute()));

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('status');
        $this->assertGuest();
        $this->assertSame(0, User::whereHas('profile', fn ($query) => $query->where($this->providerIdColumn(), $providerId))->count());
    }
}
