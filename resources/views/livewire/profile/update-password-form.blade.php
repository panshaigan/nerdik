<?php

use App\Livewire\Profile\Concerns\ReportsProfileTabValidation;
use App\Livewire\Profile\Concerns\WithAdvancedPasswordConfirmation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Js;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Component;

new class extends Component
{
    use ReportsProfileTabValidation;
    use WithAdvancedPasswordConfirmation;
    public string $password = '';
    public string $password_confirmation = '';

    /**
     * Update the password for the currently authenticated user.
     */
    public function updatePassword(): void
    {
        $this->validate([
            'password' => ['required', 'string', Password::defaults(), 'confirmed'],
        ]);

        $this->openPasswordConfirmation('confirmPasswordUpdate');
    }

    public function confirmPasswordUpdate(string $password): void
    {
        $this->passwordConfirmationPassword = $password;

        $this->reportProfileTabValidation('advanced', function (): void {
            try {
                $validated = $this->validate([
                    'passwordConfirmationPassword' => ['required', 'string', 'current_password'],
                    'password' => ['required', 'string', Password::defaults(), 'confirmed'],
                ]);
            } catch (ValidationException $e) {
                $this->reset('passwordConfirmationPassword', 'password', 'password_confirmation');

                throw $e;
            }

            Auth::user()->update([
                'password' => Hash::make($validated['password']),
            ]);

            $this->reset('passwordConfirmationPassword', 'password', 'password_confirmation');

            $this->js('window.toast('.Js::from([
                'toast' => [
                    'type' => 'success',
                    'title' => __('ui.profile.password_updated_success'),
                    'description' => '',
                    'icon' => '',
                    'css' => 'alert-success',
                    'timeout' => 4000,
                    'noProgress' => false,
                ],
            ]).')');

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

    <x-ui.form-errors :title="__('ui.status.oops')" :description="__('ui.status.fix_errors')" icon="o-face-frown" class="!mx-0 my-4" />

    <form id="ui-profile-password-form" wire:submit="updatePassword" novalidate class="ui-form ui-form-profile-password mt-6 space-y-4" data-ui="profile-password-form">
        <x-password
            wire:model="password"
            label="{{ __('ui.profile.new_password') }}"
            placeholder="{{ __('ui.profile.new_password') }}"
            name="password"
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

    <x-modal
        wire:model="confirmingPassword"
        :title="__('ui.profile.confirm_password_modal_title')"
        :subtitle="__('ui.profile.confirm_password_modal_hint')"
        class="backdrop-blur ui-modal ui-modal-password-confirm"
        box-class="ui-modal-surface"
        separator
        data-ui="profile-password-modal"
    >
        <x-ui.form-errors :title="__('ui.status.oops')" :description="__('ui.status.fix_errors')" icon="o-face-frown" class="!mx-0 mb-4" />

        <form wire:submit.prevent="runPasswordConfirmation" class="space-y-4" data-ui="profile-password-modal-form">
            <x-password
                wire:model="passwordConfirmationPassword"
                label="{{ __('ui.profile.current_password') }}"
                placeholder="{{ __('ui.profile.current_password') }}"
                name="passwordConfirmationPassword"
                error-field="passwordConfirmationPassword"
                autocomplete="current-password"
                class="ui-field ui-field-current-password"
                data-ui="profile-password-modal-input"
            />

            <div class="modal-action">
                <x-button type="button" class="btn-ghost" wire:click="cancelPasswordConfirmation" data-ui="profile-password-modal-cancel">
                    {{ __('ui.common.cancel') }}
                </x-button>
                <x-button type="submit" class="btn-primary" data-ui="profile-password-modal-confirm">
                    {{ __('ui.profile.confirm_password_continue') }}
                </x-button>
            </div>
        </form>
    </x-modal>
</section>
