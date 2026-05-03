<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Notification;
use Livewire\Volt\Volt;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response
            ->assertOk()
            ->assertSeeVolt('pages.auth.register');
    }

    public function test_new_users_can_register(): void
    {
        Notification::fake();

        $component = Volt::test('pages.auth.register')
            ->set('name', 'Test User')
            ->set('nickname', 'test-user')
            ->set('email', 'test@example.com')
            ->set('password', 'password')
            ->set('password_confirmation', 'password');

        $component->call('register');

        $component->assertRedirect(route('verification.notice', absolute: false));

        $this->assertAuthenticated();

        /** @var User|null $user */
        $user = User::where('email', 'test@example.com')->first();
        $this->assertNotNull($user);

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_registration_requires_recaptcha_response_when_recaptcha_enabled(): void
    {
        Notification::fake();

        Config::set('services.recaptcha.enabled', true);
        Config::set('captcha.sitekey', 'test-site-key');
        Config::set('captcha.secret', 'test-secret-key');

        $component = Volt::test('pages.auth.register')
            ->set('name', 'Test User')
            ->set('nickname', 'test-user')
            ->set('email', 'test@example.com')
            ->set('password', 'password')
            ->set('password_confirmation', 'password');

        $component->call('register');

        $component->assertHasErrors(['gRecaptchaResponse']);
        Notification::assertNothingSent();
    }
}
