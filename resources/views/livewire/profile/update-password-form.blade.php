<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Component;

new class extends Component
{
    public string $current_password = '';
    public string $password = '';
    public string $password_confirmation = '';

    /**
     * Update the password for the currently authenticated user.
     */
    public function updatePassword(): void
    {
        try {
            $validated = $this->validate([
                'current_password' => ['required', 'string', 'current_password'],
                'password' => ['required', 'string', Password::defaults(), 'confirmed'],
            ]);
        } catch (ValidationException $e) {
            $this->reset('current_password', 'password', 'password_confirmation');

            throw $e;
        }

        Auth::user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        $this->reset('current_password', 'password', 'password_confirmation');

        $this->dispatch('password-updated');
    }
}; ?>

<section id="ui-profile-password-section" class="ui-profile-section ui-profile-password" data-ui="profile-password-section">
    <header>
        <h2 class="text-lg font-medium text-base-content">
            {{ __('Update Password') }}
        </h2>

        <p class="mt-1 text-sm text-base-content/70">
            {{ __('Ensure your account is using a long, random password to stay secure.') }}
        </p>
    </header>

    <form id="ui-profile-password-form" wire:submit="updatePassword" class="ui-form ui-form-profile-password mt-6 space-y-4" data-ui="profile-password-form">
        <x-password
            wire:model="current_password"
            label="{{ __('Current Password') }}"
            name="current_password"
            error-field="current_password"
            autocomplete="current-password"
            class="ui-field ui-field-current-password"
            data-ui="profile-current-password"
        />

        <x-password
            wire:model="password"
            label="{{ __('New Password') }}"
            name="password"
            error-field="password"
            autocomplete="new-password"
            class="ui-field ui-field-new-password"
            data-ui="profile-new-password"
        />

        <x-password
            wire:model="password_confirmation"
            label="{{ __('Confirm Password') }}"
            name="password_confirmation"
            error-field="password_confirmation"
            autocomplete="new-password"
            class="ui-field ui-field-password-confirmation"
            data-ui="profile-password-confirmation"
        />

        <div class="flex items-center gap-4">
            <x-button id="ui-profile-password-submit" class="btn-primary ui-action ui-action-submit" type="submit" data-ui="profile-password-submit">{{ __('Save') }}</x-button>

            <x-action-message class="me-3" on="password-updated">
                {{ __('Saved.') }}
            </x-action-message>
        </div>
    </form>
</section>
