<?php

namespace Tests\Feature\Auth;

use Anhskohbo\NoCaptcha\NoCaptcha;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Notification;
use Livewire\Volt\Volt;
use Mockery;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_password_link_screen_can_be_rendered(): void
    {
        $response = $this->get('/forgot-password');

        $response
            ->assertSeeVolt('pages.auth.forgot-password')
            ->assertStatus(200);
    }

    public function test_reset_password_link_can_be_requested(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        Volt::test('pages.auth.forgot-password')
            ->set('email', $user->email)
            ->call('sendPasswordResetLink');

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_reset_password_screen_can_be_rendered(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        Volt::test('pages.auth.forgot-password')
            ->set('email', $user->email)
            ->call('sendPasswordResetLink');

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) {
            $response = $this->get('/reset-password/'.$notification->token);

            $response
                ->assertSeeVolt('pages.auth.reset-password')
                ->assertStatus(200);

            return true;
        });
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        Volt::test('pages.auth.forgot-password')
            ->set('email', $user->email)
            ->call('sendPasswordResetLink');

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $component = Volt::test('pages.auth.reset-password', ['token' => $notification->token])
                ->set('email', $user->email)
                ->set('password', 'password')
                ->set('password_confirmation', 'password');

            $component->call('resetPassword');

            $component
                ->assertRedirect('/login')
                ->assertHasNoErrors();

            return true;
        });
    }

    public function test_reset_password_requires_recaptcha_response_when_recaptcha_enabled(): void
    {
        Notification::fake();

        Config::set('services.recaptcha.enabled', true);
        Config::set('captcha.sitekey', 'test-site-key');
        Config::set('captcha.secret', 'test-secret-key');

        $user = User::factory()->create();

        Volt::test('pages.auth.forgot-password')
            ->set('email', $user->email)
            ->call('sendPasswordResetLink')
            ->assertHasErrors(['gRecaptchaResponse']);

        Notification::assertNothingSent();
    }

    public function test_forgot_password_form_errors_do_not_verify_recaptcha_when_recaptcha_enabled(): void
    {
        Notification::fake();
        $this->enableRecaptchaForAuth();

        $verifyCalled = false;
        $this->mockCaptchaVerifier(function () use (&$verifyCalled): bool {
            $verifyCalled = true;

            return true;
        });

        Volt::test('pages.auth.forgot-password')
            ->set('email', 'not-an-email')
            ->set('gRecaptchaResponse', 'valid-token')
            ->call('sendPasswordResetLink')
            ->assertHasErrors(['email']);

        $this->assertFalse($verifyCalled);
        Notification::assertNothingSent();
    }

    public function test_forgot_password_can_retry_after_form_error_with_same_recaptcha_token(): void
    {
        Notification::fake();
        $this->enableRecaptchaForAuth();

        $this->mockCaptchaVerifier(fn (string $response): bool => $response === 'valid-token');

        $user = User::factory()->create();

        $component = Volt::test('pages.auth.forgot-password')
            ->set('email', 'not-an-email')
            ->set('gRecaptchaResponse', 'valid-token');

        $component->call('sendPasswordResetLink')->assertHasErrors(['email']);

        $component
            ->set('email', $user->email)
            ->call('sendPasswordResetLink')
            ->assertHasNoErrors();

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_forgot_password_clears_recaptcha_state_when_captcha_verification_fails(): void
    {
        Notification::fake();
        $this->enableRecaptchaForAuth();

        $this->mockCaptchaVerifier(fn (): bool => false);

        $user = User::factory()->create();

        $component = Volt::test('pages.auth.forgot-password')
            ->set('email', $user->email)
            ->set('gRecaptchaResponse', 'invalid-token');

        $component->call('sendPasswordResetLink')->assertHasErrors(['gRecaptchaResponse']);

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
