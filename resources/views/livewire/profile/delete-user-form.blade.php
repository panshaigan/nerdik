<?php

use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component
{
    public string $password = '';

    public bool $confirmingUserDeletion = false;

    /**
     * Delete the currently authenticated user.
     */
    public function deleteUser(Logout $logout): void
    {
        $this->validate([
            'password' => ['required', 'string', 'current_password'],
        ]);

        tap(Auth::user(), $logout(...))->delete();

        $this->redirect('/', navigate: true);
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

    <x-button id="ui-profile-delete-open" type="button" class="btn-error ui-action ui-action-delete" wire:click="$set('confirmingUserDeletion', true)" data-ui="profile-delete-open">
        {{ __('ui.profile.delete_account_title') }}
    </x-button>

    <x-modal wire:model="confirmingUserDeletion" :title="__('ui.profile.delete_account_confirm_title')" :subtitle="__('ui.profile.delete_account_confirm_body')" id="ui-profile-delete-modal" class="ui-modal ui-modal-delete" data-ui="profile-delete-modal">
        <form id="ui-profile-delete-form" wire:submit="deleteUser" class="ui-form ui-form-profile-delete space-y-4" data-ui="profile-delete-form">
            <x-password
                wire:model="password"
                label="{{ __('ui.common.password') }}"
                name="password"
                error-field="password"
                placeholder="{{ __('ui.common.password') }}" class="ui-field ui-field-password" data-ui="profile-delete-password"
            />

            <div class="modal-action">
                <x-button id="ui-profile-delete-cancel" type="button" class="btn-ghost ui-action ui-action-cancel" wire:click="$set('confirmingUserDeletion', false)" data-ui="profile-delete-cancel">
                    {{ __('ui.common.cancel') }}
                </x-button>
                <x-button id="ui-profile-delete-submit" type="submit" class="btn-error ui-action ui-action-submit-delete" data-ui="profile-delete-submit">
                    {{ __('ui.profile.delete_account_title') }}
                </x-button>
            </div>
        </form>
    </x-modal>
</section>
