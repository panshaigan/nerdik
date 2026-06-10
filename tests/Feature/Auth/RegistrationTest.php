<?php

namespace Tests\Feature\Auth;

use Anhskohbo\NoCaptcha\NoCaptcha;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Notification;
use Livewire\Volt\Volt;
use Mockery;
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
            ->set('nickname', 'test-user')
            ->set('email', 'test@example.com')
            ->set('password', 'password')
            ->set('password_confirmation', 'password')
            ->set('timezone', 'Europe/Warsaw');

        $component->call('register');

        $component->assertRedirect(route('verification.notice', absolute: false));

        $this->assertAuthenticated();

        /** @var User|null $user */
        $user = User::where('email', 'test@example.com')->first();
        $this->assertNotNull($user);
        $this->assertSame('test-user', $user->nickname);
        $this->assertNull($user->name);
        $this->assertSame('Europe/Warsaw', $user->profile?->timezone);

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_registration_requires_recaptcha_response_when_recaptcha_enabled(): void
    {
        Notification::fake();

        Config::set('services.recaptcha.enabled', true);
        Config::set('captcha.sitekey', 'test-site-key');
        Config::set('captcha.secret', 'test-secret-key');

        $component = Volt::test('pages.auth.register')
            ->set('nickname', 'test-user')
            ->set('email', 'test@example.com')
            ->set('password', 'password')
            ->set('password_confirmation', 'password');

        $component->call('register');

        $component->assertHasErrors(['gRecaptchaResponse']);
        Notification::assertNothingSent();
    }

    public function test_registration_form_errors_do_not_verify_recaptcha_when_recaptcha_enabled(): void
    {
        Notification::fake();
        $this->enableRecaptchaForAuth();

        $verifyCalled = false;
        $this->mockCaptchaVerifier(function () use (&$verifyCalled): bool {
            $verifyCalled = true;

            return true;
        });

        Volt::test('pages.auth.register')
            ->set('nickname', 'test-user')
            ->set('email', 'test@example.com')
            ->set('password', 'password')
            ->set('password_confirmation', 'mismatch')
            ->set('gRecaptchaResponse', 'valid-token')
            ->call('register')
            ->assertHasErrors(['password']);

        $this->assertFalse($verifyCalled);
        Notification::assertNothingSent();
    }

    public function test_registration_can_retry_after_form_error_with_same_recaptcha_token(): void
    {
        Notification::fake();
        $this->enableRecaptchaForAuth();

        $this->mockCaptchaVerifier(fn (string $response): bool => $response === 'valid-token');

        $component = Volt::test('pages.auth.register')
            ->set('nickname', 'test-user')
            ->set('email', 'test@example.com')
            ->set('password', 'password')
            ->set('password_confirmation', 'mismatch')
            ->set('gRecaptchaResponse', 'valid-token');

        $component->call('register')->assertHasErrors(['password']);

        $component
            ->set('password_confirmation', 'password')
            ->call('register')
            ->assertRedirect(route('verification.notice', absolute: false));

        $this->assertAuthenticated();
        Notification::assertSentTo(User::where('email', 'test@example.com')->first(), VerifyEmail::class);
    }

    public function test_registration_clears_recaptcha_state_when_captcha_verification_fails(): void
    {
        Notification::fake();
        $this->enableRecaptchaForAuth();

        $this->mockCaptchaVerifier(fn (): bool => false);

        $component = Volt::test('pages.auth.register')
            ->set('nickname', 'test-user')
            ->set('email', 'test@example.com')
            ->set('password', 'password')
            ->set('password_confirmation', 'password')
            ->set('gRecaptchaResponse', 'invalid-token');

        $component->call('register')->assertHasErrors(['gRecaptchaResponse']);

        $this->assertSame('', $component->get('gRecaptchaResponse'));
        Notification::assertNothingSent();
    }

    private function enableRecaptchaForAuth(): void
    {
        Config::set('services.recaptcha.enabled', true);
        Config::set('captcha.sitekey', 'test-site-key');
        Config::set('captcha.secret', 'test-secret-key');
    }

    /**
     * @param  callable(string, ?string): bool  $callback
     */
    private function mockCaptchaVerifier(callable $callback): void
    {
        $captcha = Mockery::mock(NoCaptcha::class)->shouldIgnoreMissing();
        $captcha->shouldReceive('verifyResponse')->andReturnUsing($callback);
        $this->app->instance('captcha', $captcha);
    }
}
