<?php

use App\Livewire\Concerns\EnsuresRecaptchaVerifiedWhenEnabled;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    use EnsuresRecaptchaVerifiedWhenEnabled;

    public string $email = '';

    protected function recaptchaDataCallback(): string
    {
        return 'nerdikAuthForgotRecaptcha';
    }

    /**
     * Send a password reset link to the provided email address.
     */
    public function sendPasswordResetLink(): void
    {
        $this->validate($this->rulesIncludingRecaptchaIfEnabled([
            'email' => ['required', 'string', 'email'],
        ]));

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
        $this->clearRecaptchaState();

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

@php
    use Anhskohbo\NoCaptcha\Facades\NoCaptcha;
@endphp

<div id="ui-auth-forgot-root" class="ui-auth ui-auth-forgot" data-ui="auth-forgot-root">
    <div class="mb-4 text-sm text-base-content/80">
        {{ __('ui.auth.forgot_password_intro') }}
    </div>

    @if (auth_recaptcha_enforced())
        @push('scripts')
            {!! NoCaptcha::renderJs() !!}
        @endpush
    @endif

    <x-flash-status class="mb-4" :status="session('status')" />

    <form id="ui-auth-forgot-form" wire:submit="sendPasswordResetLink" class="ui-form ui-form-auth-forgot space-y-4" data-ui="auth-forgot-form">
        <x-input
            wire:model="email"
            label="{{ __('ui.common.email') }}"
            type="email"
            name="email"
            error-field="email"
            required
            autofocus
            class="ui-field ui-field-email"
            data-ui="auth-forgot-email"
        />

        @if (auth_recaptcha_enforced())
            <div wire:ignore class="flex min-h-[78px] flex-col justify-center" data-ui="auth-forgot-recaptcha">
                {!! NoCaptcha::display(['data-callback' => $this->recaptchaDataCallback()]) !!}
            </div>

            @error('gRecaptchaResponse')
                <div class="text-sm font-medium text-error" role="alert">{{ $message }}</div>
            @enderror
        @endif

        <div class="flex justify-end">
            <x-button id="ui-auth-forgot-submit" class="btn-primary ui-action ui-action-submit" type="submit" data-ui="auth-forgot-submit">{{ __('ui.auth.email_password_reset_link') }}</x-button>
        </div>
    </form>
</div>
