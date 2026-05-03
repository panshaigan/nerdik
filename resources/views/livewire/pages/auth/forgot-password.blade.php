<?php

use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public string $email = '';

    /**
     * Send a password reset link to the provided email address.
     */
    public function sendPasswordResetLink(): void
    {
        $this->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        $this->ensurePasswordResetLinkIsNotRateLimited();

        RateLimiter::hit($this->passwordResetThrottleKey());

        $status = Password::sendResetLink(
            $this->only('email')
        );

        if ($status != Password::RESET_LINK_SENT) {
            $this->addError('email', __($status));

            return;
        }

        $this->reset('email');

        session()->flash('status', __($status));
    }

    /**
     * @throws ValidationException
     */
    protected function ensurePasswordResetLinkIsNotRateLimited(): void
    {
        $key = $this->passwordResetThrottleKey();

        if (! RateLimiter::tooManyAttempts($key, 5)) {
            return;
        }

        $seconds = RateLimiter::availableIn($key);

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    protected function passwordResetThrottleKey(): string
    {
        return Str::transliterate(Str::lower($this->email).'|'.request()->ip());
    }
}; ?>

<div id="ui-auth-forgot-root" class="ui-auth ui-auth-forgot" data-ui="auth-forgot-root">
    <div class="mb-4 text-sm text-base-content/80">
        {{ __('Forgot your password? No problem. Just let us know your email address and we will email you a password reset link that will allow you to choose a new one.') }}
    </div>

    <x-flash-status class="mb-4" :status="session('status')" />

    <form id="ui-auth-forgot-form" wire:submit="sendPasswordResetLink" class="ui-form ui-form-auth-forgot space-y-4" data-ui="auth-forgot-form">
        <x-input
            wire:model="email"
            label="{{ __('Email') }}"
            type="email"
            name="email"
            error-field="email"
            required
            autofocus
            class="ui-field ui-field-email"
            data-ui="auth-forgot-email"
        />

        <div class="flex justify-end">
            <x-button id="ui-auth-forgot-submit" class="btn-primary ui-action ui-action-submit" type="submit" data-ui="auth-forgot-submit">{{ __('Email Password Reset Link') }}</x-button>
        </div>
    </form>
</div>
