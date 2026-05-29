<?php

use App\Livewire\Concerns\EnsuresRecaptchaVerifiedWhenEnabled;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    use EnsuresRecaptchaVerifiedWhenEnabled;

    public string $nickname = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public string $timezone = '';

    protected function recaptchaDataCallback(): string
    {
        return 'nerdikAuthRegisterRecaptcha';
    }

    /**
     * Handle an incoming registration request.
     */
    public function register(): void
    {
        $validated = $this->validate($this->rulesIncludingRecaptchaIfEnabled([
            'nickname' => ['required', 'string', 'max:255', 'unique:'.User::class],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
            'timezone' => ['nullable', 'string', 'timezone'],
        ]));

        unset($validated['gRecaptchaResponse']);

        $this->ensureRegistrationIsNotRateLimited();

        RateLimiter::hit($this->registrationThrottleKey());

        $validated['password'] = Hash::make($validated['password']);

        event(new Registered($user = User::create($validated)));
        $user->profile()->updateOrCreate(
            ['user_id' => $user->id],
            ['timezone' => ($validated['timezone'] ?? '') !== '' ? $validated['timezone'] : null]
        );

        Auth::login($user);

        $this->redirect(route('verification.notice', absolute: false), navigate: true);
    }

    /**
     * @throws ValidationException
     */
    protected function ensureRegistrationIsNotRateLimited(): void
    {
        $key = $this->registrationThrottleKey();

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

    protected function registrationThrottleKey(): string
    {
        return 'registration-submit:'.request()->ip();
    }
}; ?>

@php
    use Anhskohbo\NoCaptcha\Facades\NoCaptcha;
@endphp

<div id="ui-auth-register-root" class="ui-auth ui-auth-register" data-ui="auth-register-root">
    @if (config('services.google.client_id'))
        <div class="mb-4">
            {{-- OAuth must use full document navigation; wire:navigate would fetch redirect → CORS on accounts.google.com --}}
            <x-button id="ui-auth-register-google" :link="route('google.redirect')" :no-wire-navigate="true" class="btn-outline w-full gap-2 border-base-300 ui-action ui-action-google" data-ui="auth-register-google">
                <svg class="h-5 w-5" viewBox="0 0 24 24"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>
                {{ __('ui.auth.register_with_google') }}
            </x-button>
        </div>
    @endif

    @if (config('services.facebook.client_id'))
        <div class="mb-4">
            {{-- OAuth must use full document navigation; wire:navigate would fetch redirect → CORS on facebook.com --}}
            <x-button id="ui-auth-register-facebook" :link="route('facebook.redirect')" :no-wire-navigate="true" class="btn-outline w-full gap-2 border-base-300 ui-action ui-action-facebook" data-ui="auth-register-facebook">
                <svg class="h-5 w-5" viewBox="0 0 24 24"><path fill="#1877F2" d="M24 12.073C24 5.405 18.627 0 12 0S0 5.405 0 12.073C0 18.1 4.388 23.094 10.125 24v-8.437H7.078v-3.49h3.047V9.413c0-3.017 1.792-4.683 4.533-4.683 1.313 0 2.686.235 2.686.235v2.971h-1.513c-1.491 0-1.956.93-1.956 1.886v2.262h3.328l-.532 3.49h-2.796V24C19.612 23.094 24 18.1 24 12.073z"/></svg>
                {{ __('ui.auth.register_with_facebook') }}
            </x-button>
        </div>
    @endif

    @if (config('services.google.client_id') || config('services.facebook.client_id'))
        <div class="relative my-4">
            <div class="absolute inset-0 flex items-center"><div class="w-full border-t border-base-300"></div></div>
            <div class="relative flex justify-center text-sm"><span class="bg-base-100 px-2 text-base-content/60">{{ __('ui.common.or') }}</span></div>
        </div>
    @endif

    @if (auth_recaptcha_enforced())
        @push('scripts')
            {!! NoCaptcha::renderJs() !!}
        @endpush
    @endif

    <form id="ui-auth-register-form" wire:submit="register" class="ui-form ui-form-auth-register space-y-4" data-ui="auth-register-form">
        <input type="hidden" wire:model="timezone" data-register-timezone />
        <x-input
            wire:model="nickname"
            label="{{ __('ui.auth.nickname') }}"
            type="text"
            name="nickname"
            error-field="nickname"
            required
            autofocus
            autocomplete="nickname"
            class="ui-field ui-field-nickname"
            data-ui="auth-register-nickname"
        />

        <x-input
            wire:model="email"
            label="{{ __('ui.common.email') }}"
            type="email"
            name="email"
            error-field="email"
            required
            autocomplete="username"
            class="ui-field ui-field-email"
            data-ui="auth-register-email"
        />

        <x-password
            wire:model="password"
            label="{{ __('ui.common.password') }}"
            name="password"
            error-field="password"
            required
            autocomplete="new-password"
            class="ui-field ui-field-password"
            data-ui="auth-register-password"
        />

        <x-password
            wire:model="password_confirmation"
            label="{{ __('ui.common.confirm_password') }}"
            name="password_confirmation"
            error-field="password_confirmation"
            required
            autocomplete="new-password"
            class="ui-field ui-field-password-confirmation"
            data-ui="auth-register-password-confirmation"
        />

        @if (auth_recaptcha_enforced())
            <div wire:ignore class="flex min-h-[78px] flex-col justify-center" data-ui="auth-register-recaptcha">
                {!! NoCaptcha::display(['data-callback' => $this->recaptchaDataCallback()]) !!}
            </div>

            @error('gRecaptchaResponse')
                <div class="text-sm font-medium text-error" role="alert">{{ $message }}</div>
            @enderror
        @endif

        <div class="flex items-center justify-end gap-3">
            <a class="link link-primary text-sm" href="{{ route('login') }}" wire:navigate>
                {{ __('ui.auth.already_registered') }}
            </a>

            <x-button id="ui-auth-register-submit" class="btn-primary ui-action ui-action-submit" type="submit" data-ui="auth-register-submit">{{ __('ui.auth.register') }}</x-button>
        </div>
    </form>
</div>

@push('scripts')
<script>
(() => {
    let registerTimezoneAbort;
    function initRegisterTimezone() {
        registerTimezoneAbort?.abort();
        registerTimezoneAbort = new AbortController();
        const signal = registerTimezoneAbort.signal;
        const timezoneInput = document.querySelector('[data-register-timezone]');
        if (!timezoneInput) {
            return;
        }

        let timezone = '';
        try {
            timezone = Intl.DateTimeFormat().resolvedOptions().timeZone || '';
        } catch (error) {
            timezone = '';
        }

        if (timezone !== '') {
            document.cookie = `browser_timezone=${encodeURIComponent(timezone)}; path=/; max-age=31536000; samesite=lax`;
            timezoneInput.value = timezone;
            timezoneInput.dispatchEvent(new Event('input', { bubbles: true }));
            timezoneInput.dispatchEvent(new Event('change', { bubbles: true }));

            const enhanceLink = (selector) => {
                const button = document.querySelector(selector);
                if (!button) {
                    return;
                }
                const href = button.getAttribute('href');
                if (!href) {
                    return;
                }
                const url = new URL(href, window.location.origin);
                if (!url.searchParams.has('tz')) {
                    url.searchParams.set('tz', timezone);
                    button.setAttribute('href', url.pathname + url.search);
                }
            };

            enhanceLink('[data-ui="auth-register-google"]');
            enhanceLink('[data-ui="auth-register-facebook"]');
        }
    }

    document.addEventListener('livewire:navigating', () => registerTimezoneAbort?.abort());
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initRegisterTimezone, { once: true });
    } else {
        initRegisterTimezone();
    }
    document.addEventListener('livewire:navigated', initRegisterTimezone);
})();
</script>
@endpush
