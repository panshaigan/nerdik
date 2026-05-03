<?php

use App\Livewire\Forms\LoginForm;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public LoginForm $form;

    /**
     * Handle an incoming authentication request.
     */
    public function login(): void
    {
        $this->validate();

        $this->form->authenticate();

        Session::regenerate();

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div id="ui-auth-login-root" class="ui-auth ui-auth-login" data-ui="auth-login-root">
    <x-flash-status class="mb-4" :status="session('status')" />

    @if (config('services.google.client_id'))
        <div class="mb-4">
            {{-- OAuth must use full document navigation; wire:navigate would fetch redirect → CORS on accounts.google.com --}}
            <x-button id="ui-auth-login-google" :link="route('google.redirect')" :no-wire-navigate="true" class="btn-outline w-full gap-2 border-base-300 ui-action ui-action-google" data-ui="auth-login-google">
                <svg class="h-5 w-5" viewBox="0 0 24 24"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>
                {{ __('Log in with Google') }}
            </x-button>
        </div>
        <div class="relative my-4">
            <div class="absolute inset-0 flex items-center"><div class="w-full border-t border-base-300"></div></div>
            <div class="relative flex justify-center text-sm"><span class="bg-base-100 px-2 text-base-content/60">{{ __('or') }}</span></div>
        </div>
    @endif

    <form id="ui-auth-login-form" wire:submit="login" class="ui-form ui-form-auth-login space-y-4" data-ui="auth-login-form">
        <x-input
            wire:model="form.email"
            label="{{ __('Email') }}"
            type="email"
            name="email"
            error-field="form.email"
            required
            autofocus
            autocomplete="username"
            class="ui-field ui-field-email"
            data-ui="auth-login-email"
        />

        <x-password
            wire:model="form.password"
            label="{{ __('Password') }}"
            name="password"
            error-field="form.password"
            required
            autocomplete="current-password"
            class="ui-field ui-field-password"
            data-ui="auth-login-password"
        />

        <x-checkbox
            wire:model="form.remember"
            id="remember"
            name="remember"
            :label="__('Remember me')"
            class="ui-field ui-field-remember"
            data-ui="auth-login-remember"
        />

        <div class="flex items-center justify-end gap-3">
            @if (Route::has('password.request'))
                <a class="link link-primary text-sm" href="{{ route('password.request') }}" wire:navigate>
                    {{ __('Forgot your password?') }}
                </a>
            @endif

            <x-button id="ui-auth-login-submit" class="btn-primary ui-action ui-action-submit" type="submit" data-ui="auth-login-submit">{{ __('Log in') }}</x-button>
        </div>
    </form>
</div>
