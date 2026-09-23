<?php

declare(strict_types=1);

namespace Tests\Feature\Feedback;

use Anhskohbo\NoCaptcha\NoCaptcha;
use App\Enums\FeedbackStatus;
use App\Enums\FeedbackType;
use App\Livewire\Feedback\FeedbackModal;
use App\Models\Feedback;
use App\Models\User;
use App\Notifications\FeedbackReceivedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class FeedbackModalTest extends TestCase
{
    use RefreshDatabase;

    public function test_type_options_use_mary_select_shape(): void
    {
        $options = (new FeedbackModal)->typeOptions();

        $this->assertNotEmpty($options);

        foreach ($options as $option) {
            $this->assertArrayHasKey('id', $option);
            $this->assertArrayHasKey('name', $option);
            $this->assertContains($option['id'], FeedbackType::values());
            $this->assertNotSame('', trim((string) $option['name']));
        }
    }

    public function test_authenticated_user_can_submit_feedback_and_admins_are_notified(): void
    {
        Notification::fake();

        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(FeedbackModal::class)
            ->call('openModal', 'https://example.test/page')
            ->assertSet('open', true)
            ->assertSee(__('feedback.modal.cancel'))
            ->assertSee(__('feedback.modal.submit'))
            ->assertSeeHtml('data-ui="overlay-sheet"')
            ->assertSet('type', FeedbackType::Question->value)
            ->set('type', FeedbackType::Bug->value)
            ->set('subject', 'Broken button')
            ->set('body', '<p>The submit button does nothing.</p>')
            ->set('pageUrl', 'https://example.test/page')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('open', false);

        $this->assertDatabaseHas(Feedback::class, [
            'type' => FeedbackType::Bug->value,
            'subject' => 'Broken button',
            'user_id' => $user->id,
            'status' => FeedbackStatus::Open->value,
        ]);

        Notification::assertSentTo($admin, FeedbackReceivedNotification::class);
    }

    public function test_authenticated_submit_does_not_require_captcha_when_enabled(): void
    {
        Notification::fake();
        User::factory()->admin()->create();
        $user = User::factory()->create();

        $this->enableRecaptcha();

        Livewire::actingAs($user)
            ->test(FeedbackModal::class)
            ->call('openModal')
            ->set('type', FeedbackType::Question->value)
            ->set('subject', 'How do tags work?')
            ->set('body', '<p>Need a short explanation.</p>')
            ->call('submit')
            ->assertHasNoErrors(['gRecaptchaResponse']);
    }

    public function test_guest_can_submit_feedback_when_captcha_disabled(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();

        Livewire::test(FeedbackModal::class)
            ->call('openModal')
            ->set('type', FeedbackType::Feature->value)
            ->set('subject', 'Dark mode toggle')
            ->set('body', '<p>Please add a light theme option.</p>')
            ->set('email', 'guest@example.com')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('open', false);

        $this->assertDatabaseHas(Feedback::class, [
            'email' => 'guest@example.com',
            'user_id' => null,
            'subject' => 'Dark mode toggle',
        ]);

        Notification::assertSentTo($admin, FeedbackReceivedNotification::class);
    }

    public function test_subject_is_optional(): void
    {
        Notification::fake();
        User::factory()->admin()->create();
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(FeedbackModal::class)
            ->call('openModal')
            ->set('body', '<p>Just a message with no subject.</p>')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('open', false);

        $this->assertDatabaseHas(Feedback::class, [
            'user_id' => $user->id,
            'type' => FeedbackType::Question->value,
            'subject' => '',
        ]);
    }

    public function test_guest_submit_requires_email(): void
    {
        Livewire::test(FeedbackModal::class)
            ->call('openModal')
            ->set('type', FeedbackType::Question->value)
            ->set('subject', 'How do I join?')
            ->set('body', '<p>Need help joining an event.</p>')
            ->call('submit')
            ->assertHasErrors(['email']);
    }

    public function test_guest_submit_requires_captcha_when_enabled(): void
    {
        $this->enableRecaptcha();

        Livewire::test(FeedbackModal::class)
            ->call('openModal')
            ->assertSeeHtml('data-ui="feedback-body"')
            ->assertSeeHtml('<textarea')
            ->assertSeeHtml('data-ui="feedback-recaptcha"')
            ->assertSeeHtml('data-nerdik-recaptcha')
            ->assertSeeHtml('data-sitekey="test-site-key"')
            ->set('type', FeedbackType::Bug->value)
            ->set('subject', 'Captcha required')
            ->set('body', '<p>Guest body</p>')
            ->set('email', 'guest@example.com')
            ->call('submit')
            ->assertHasErrors(['gRecaptchaResponse']);
    }

    public function test_authenticated_feedback_uses_editor_without_captcha(): void
    {
        $this->enableRecaptcha();

        Livewire::actingAs(User::factory()->create())
            ->test(FeedbackModal::class)
            ->call('openModal')
            ->assertSeeHtml('data-ui="feedback-body"')
            ->assertSeeHtml('type="textarea"')
            ->assertDontSeeHtml('data-ui="feedback-recaptcha"')
            ->assertDontSeeHtml('data-nerdik-recaptcha');
    }

    public function test_submit_is_rate_limited(): void
    {
        Notification::fake();
        User::factory()->admin()->create();
        $user = User::factory()->create();

        Config::set('feedback.submit_max_attempts', 1);
        Config::set('feedback.submit_decay_seconds', 3600);

        $component = Livewire::actingAs($user)->test(FeedbackModal::class);

        $component
            ->call('openModal')
            ->set('type', FeedbackType::Other->value)
            ->set('subject', 'First')
            ->set('body', '<p>First message</p>')
            ->call('submit')
            ->assertHasNoErrors();

        $component
            ->call('openModal')
            ->set('type', FeedbackType::Other->value)
            ->set('subject', 'Second')
            ->set('body', '<p>Second message</p>')
            ->call('submit')
            ->assertHasErrors(['body']);

        RateLimiter::clear(Str::transliterate('feedback-submit|user:'.$user->id));
    }

    private function enableRecaptcha(): void
    {
        Config::set('services.recaptcha.enabled', true);
        Config::set('captcha.sitekey', 'test-site-key');
        Config::set('captcha.secret', 'test-secret-key');

        $captcha = Mockery::mock(NoCaptcha::class)->shouldIgnoreMissing();
        $captcha->shouldReceive('verifyResponse')->andReturn(false);
        $this->app->instance('captcha', $captcha);
    }
}
