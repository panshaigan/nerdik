<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public string $password = '';

    /**
     * Confirm the current user's password.
     */
    public function confirmPassword(): void
    {
        $this->validate([
            'password' => ['required', 'string'],
        ]);

        if (! Auth::guard('web')->validate([
            'email' => Auth::user()->email,
            'password' => $this->password,
        ])) {
            throw ValidationException::withMessages([
                'password' => __('auth.password'),
            ]);
        }

        session(['auth.password_confirmed_at' => time()]);

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div id="ui-auth-confirm-root" class="ui-auth ui-auth-confirm" data-ui="auth-confirm-root">
    <div class="mb-4 text-sm text-base-content/80">
        {{ __('ui.auth.confirm_password_intro') }}
    </div>

    <form id="ui-auth-confirm-form" wire:submit="confirmPassword" class="ui-form ui-form-auth-confirm space-y-4" data-ui="auth-confirm-form">
        <x-password
            wire:model="password"
            label="{{ __('ui.common.password') }}"
            name="password"
            error-field="password"
            required
            autocomplete="current-password"
            class="ui-field ui-field-password"
            data-ui="auth-confirm-password"
        />

        <div class="flex justify-end">
            <x-button id="ui-auth-confirm-submit" class="btn-primary ui-action ui-action-submit" type="submit" data-ui="auth-confirm-submit">{{ __('ui.common.confirm') }}</x-button>
        </div>
    </form>
</div>
