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

<div>
    <form wire:submit="resetPassword" class="space-y-4">
        <x-input
            wire:model="email"
            label="{{ __('Email') }}"
            type="email"
            name="email"
            error-field="email"
            required
            autofocus
            autocomplete="username"
        />

        <x-password
            wire:model="password"
            label="{{ __('Password') }}"
            name="password"
            error-field="password"
            required
            autocomplete="new-password"
        />

        <x-password
            wire:model="password_confirmation"
            label="{{ __('Confirm Password') }}"
            name="password_confirmation"
            error-field="password_confirmation"
            required
            autocomplete="new-password"
        />

        <div class="flex justify-end">
            <x-button class="btn-primary" type="submit">{{ __('Reset Password') }}</x-button>
        </div>
    </form>
</div>
