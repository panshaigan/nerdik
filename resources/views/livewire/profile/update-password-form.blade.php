<?php

use App\Livewire\Profile\Concerns\ReportsProfileTabValidation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Component;

new class extends Component
{
    use ReportsProfileTabValidation;
    public string $current_password = '';
    public string $password = '';
    public string $password_confirmation = '';

    /**
     * Update the password for the currently authenticated user.
     */
    public function updatePassword(): void
    {
        $this->reportProfileTabValidation('advanced', function (): void {
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
        });
    }
}; ?>

<section id="ui-profile-password-section" class="ui-profile-section ui-profile-password" data-ui="profile-password-section">
    <header>
        <h2 class="text-lg font-medium text-base-content">
            {{ __('ui.profile.update_password_title') }}
        </h2>

        <p class="mt-1 text-sm text-base-content/70">
            {{ __('ui.profile.update_password_hint') }}
        </p>
    </header>

    <x-ui.form-errors :title="__('ui.status.oops')" :description="__('ui.status.fix_errors')" icon="o-face-frown" class="!mx-0 mb-4" />

    <form id="ui-profile-password-form" wire:submit="updatePassword" novalidate class="ui-form ui-form-profile-password mt-6 space-y-4" data-ui="profile-password-form">
        <x-password
            wire:model="current_password"
            label="{{ __('ui.profile.current_password') }}"
            placeholder="{{ __('ui.profile.current_password') }}"
            name="current_password"
            error-field="current_password"
            autocomplete="current-password"
            class="ui-field ui-field-current-password"
            data-ui="profile-current-password"
            inline
        />

        <x-password
            wire:model="password"
            label="{{ __('ui.profile.new_password') }}"
            placeholder="{{ __('ui.profile.new_password') }}"
            name="password"
            error-field="password"
            autocomplete="new-password"
            class="ui-field ui-field-new-password"
            data-ui="profile-new-password"
            inline
        />

        <x-password
            wire:model="password_confirmation"
            label="{{ __('ui.common.confirm_password') }}"
            placeholder="{{ __('ui.common.confirm_password') }}"
            name="password_confirmation"
            error-field="password_confirmation"
            autocomplete="new-password"
            class="ui-field ui-field-password-confirmation"
            data-ui="profile-password-confirmation"
            inline
        />

        <div class="flex items-center justify-end gap-4">
            <x-action-message class="me-3" on="password-updated">
                {{ __('ui.common.saved') }}
            </x-action-message>

            <x-button id="ui-profile-password-submit" class="btn-primary ui-action ui-action-submit" type="submit" data-ui="profile-password-submit">{{ __('ui.common.save') }}</x-button>
        </div>
    </form>
</section>
