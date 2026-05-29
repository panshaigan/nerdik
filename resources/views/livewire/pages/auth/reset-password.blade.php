<?php

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    #[Locked]
    public string $token = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    /**
     * Mount the component.
     */
    public function mount(string $token): void
    {
        $this->token = $token;

        $this->email = request()->string('email');
    }

    /**
     * Reset the password for the given user.
     */
    public function resetPassword(): void
    {
        $this->validate([
            'token' => ['required'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
        ]);

        $status = Password::reset(
            $this->only('email', 'password', 'password_confirmation', 'token'),
            function ($user) {
                $user->forceFill([
                    'password' => Hash::make($this->password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status != Password::PASSWORD_RESET) {
            $this->addError('email', __($status));

            return;
        }

        Session::flash('status', __($status));

        $this->redirectRoute('login', navigate: true);
    }
}; ?>

<div id="ui-auth-reset-root" class="ui-auth ui-auth-reset" data-ui="auth-reset-root">
    <form id="ui-auth-reset-form" wire:submit="resetPassword" class="ui-form ui-form-auth-reset space-y-4" data-ui="auth-reset-form">
        <x-input
            wire:model="email"
            label="{{ __('ui.common.email') }}"
            type="email"
            name="email"
            error-field="email"
            required
            autofocus
            autocomplete="username"
            class="ui-field ui-field-email"
            data-ui="auth-reset-email"
        />

        <x-password
            wire:model="password"
            label="{{ __('ui.common.password') }}"
            name="password"
            error-field="password"
            required
            autocomplete="new-password"
            class="ui-field ui-field-password"
            data-ui="auth-reset-password"
        />

        <x-password
            wire:model="password_confirmation"
            label="{{ __('ui.common.confirm_password') }}"
            name="password_confirmation"
            error-field="password_confirmation"
            required
            autocomplete="new-password"
            class="ui-field ui-field-password-confirmation"
            data-ui="auth-reset-password-confirmation"
        />

        <div class="flex justify-end">
            <x-button id="ui-auth-reset-submit" class="btn-primary ui-action ui-action-submit" type="submit" data-ui="auth-reset-submit">{{ __('ui.auth.reset_password') }}</x-button>
        </div>
    </form>
</div>
