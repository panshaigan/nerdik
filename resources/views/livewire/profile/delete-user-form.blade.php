<?php

use App\Livewire\Actions\Logout;
use App\Livewire\Profile\Concerns\ReportsProfileTabValidation;
use App\Livewire\Profile\Concerns\WithAdvancedPasswordConfirmation;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component
{
    use ReportsProfileTabValidation;
    use WithAdvancedPasswordConfirmation;

    /**
     * Delete the currently authenticated user.
     */
    public function promptDeleteUser(): void
    {
        $this->resetValidation();
        $this->openPasswordConfirmation('deleteUser');
    }

    public function deleteUser(string $password): void
    {
        $this->passwordConfirmationPassword = $password;

        $this->reportProfileTabValidation('advanced', function (): void {
            $this->validate([
                'passwordConfirmationPassword' => ['required', 'string', 'current_password'],
            ]);

            $logout = app(Logout::class);
            tap(Auth::user(), $logout(...))->delete();

            $this->redirect('/', navigate: true);
        });
    }
}; ?>

<section id="ui-profile-delete-section" class="ui-profile-section ui-profile-delete space-y-6" data-ui="profile-delete-section">
    <header>
        <h2 class="text-lg font-medium text-base-content">
            {{ __('ui.profile.delete_account_title') }}
        </h2>

        <p class="mt-1 text-sm text-base-content/70">
            {{ __('ui.profile.delete_account_intro') }}
        </p>
    </header>

    <x-button id="ui-profile-delete-open" type="button" class="btn-error ui-action ui-action-delete" wire:click="promptDeleteUser" data-ui="profile-delete-open">
        {{ __('ui.profile.delete_account_title') }}
    </x-button>

    <x-modal
        wire:model="confirmingPassword"
        :title="__('ui.profile.confirm_password_modal_title')"
        :subtitle="__('ui.profile.confirm_password_modal_hint')"
        id="ui-profile-delete-modal"
        class="backdrop-blur ui-modal ui-modal-delete"
        box-class="ui-modal-surface"
        separator
        data-ui="profile-delete-modal"
    >
        <x-ui.form-errors :title="__('ui.status.oops')" :description="__('ui.status.fix_errors')" icon="o-face-frown" class="!mx-0 mb-4" />
        <form id="ui-profile-delete-form" wire:submit.prevent="runPasswordConfirmation" novalidate class="ui-form ui-form-profile-delete space-y-4" data-ui="profile-delete-form">
            <x-password
                wire:model="passwordConfirmationPassword"
                label="{{ __('ui.profile.current_password') }}"
                name="passwordConfirmationPassword"
                error-field="passwordConfirmationPassword"
                placeholder="{{ __('ui.profile.current_password') }}"
                autocomplete="current-password"
                class="ui-field ui-field-password"
                data-ui="profile-delete-password"
            />

            <div class="modal-action">
                <x-button id="ui-profile-delete-cancel" type="button" class="btn-ghost ui-action ui-action-cancel" wire:click="cancelPasswordConfirmation" data-ui="profile-delete-cancel">
                    {{ __('ui.common.cancel') }}
                </x-button>
                <x-button id="ui-profile-delete-submit" type="submit" class="btn-error ui-action ui-action-submit-delete" data-ui="profile-delete-submit">
                    {{ __('ui.profile.confirm_password_continue') }}
                </x-button>
            </div>
        </form>
    </x-modal>
</section>
