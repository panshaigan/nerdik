<?php

namespace Tests\Feature\Profile;

use App\Models\User;
use App\Notifications\VerifyPendingEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Volt\Volt;
use Tests\TestCase;

class UpdateEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_request_email_change_with_valid_password(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'current@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->actingAs($user);

        Volt::test('profile.update-email-form')
            ->set('new_email', 'new@example.com')
            ->call('requestEmailChange')
            ->set('passwordConfirmationPassword', 'password')
            ->call('runPasswordConfirmation')
            ->assertHasNoErrors();

        $user->refresh();

        $this->assertSame('new@example.com', $user->pending_email);
        $this->assertSame('current@example.com', $user->email);

        Notification::assertSentOnDemand(
            VerifyPendingEmailNotification::class,
            function (VerifyPendingEmailNotification $notification, array $channels, object $notifiable) use ($user): bool {
                return in_array('mail', $channels, true)
                    && ($notifiable->routes['mail'] ?? null) === 'new@example.com'
                    && $notification->user->is($user);
            }
        );
    }

    public function test_wrong_password_is_rejected_when_requesting_email_change(): void
    {
        $user = User::factory()->create([
            'email' => 'current@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->actingAs($user);

        Volt::test('profile.update-email-form')
            ->set('new_email', 'new@example.com')
            ->call('requestEmailChange')
            ->set('passwordConfirmationPassword', 'wrong-password')
            ->call('runPasswordConfirmation')
            ->assertHasErrors(['passwordConfirmationPassword'])
            ->assertDispatched('profile-tab-validation-failed', tab: 'advanced');

        $this->assertNull($user->fresh()->pending_email);
    }

    public function test_duplicate_email_is_rejected(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $user = User::factory()->create([
            'email' => 'current@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->actingAs($user);

        Volt::test('profile.update-email-form')
            ->set('new_email', 'taken@example.com')
            ->call('requestEmailChange')
            ->assertHasErrors(['new_email']);
    }

    public function test_duplicate_pending_email_is_rejected(): void
    {
        User::factory()->create(['pending_email' => 'pending@example.com']);

        $user = User::factory()->create([
            'email' => 'current@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->actingAs($user);

        Volt::test('profile.update-email-form')
            ->set('new_email', 'pending@example.com')
            ->call('requestEmailChange')
            ->assertHasErrors(['new_email']);
    }

    public function test_same_email_as_current_is_rejected(): void
    {
        $user = User::factory()->create([
            'email' => 'current@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->actingAs($user);

        Volt::test('profile.update-email-form')
            ->set('new_email', 'current@example.com')
            ->call('requestEmailChange')
            ->assertHasErrors(['new_email']);
    }

    public function test_resend_is_throttled_after_six_attempts(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'current@example.com',
            'pending_email' => 'new@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->actingAs($user);

        RateLimiter::clear('email-change-resend:'.$user->id);

        for ($attempt = 0; $attempt < 6; $attempt++) {
            Volt::test('profile.update-email-form')
                ->call('resendPendingEmailVerification')
                ->assertHasNoErrors();
        }

        Volt::test('profile.update-email-form')
            ->call('resendPendingEmailVerification')
            ->assertHasErrors(['emailChangeResend']);
    }

    public function test_user_can_cancel_pending_email_change(): void
    {
        $user = User::factory()->create([
            'email' => 'current@example.com',
            'pending_email' => 'new@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->actingAs($user);

        Volt::test('profile.update-email-form')
            ->call('cancelPendingEmailChange')
            ->assertHasNoErrors();

        $this->assertNull($user->fresh()->pending_email);
    }
}
