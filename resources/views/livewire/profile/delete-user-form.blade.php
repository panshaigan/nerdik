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
            {{ __('Delete Account') }}
        </h2>

        <p class="mt-1 text-sm text-base-content/70">
            {{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Before deleting your account, please download any data or information that you wish to retain.') }}
        </p>
    </header>

    <x-button id="ui-profile-delete-open" type="button" class="btn-error ui-action ui-action-delete" wire:click="$set('confirmingUserDeletion', true)" data-ui="profile-delete-open">
        {{ __('Delete Account') }}
    </x-button>

    <x-modal wire:model="confirmingUserDeletion" :title="__('Are you sure you want to delete your account?')" :subtitle="__('Once your account is deleted, all of its resources and data will be permanently deleted. Please enter your password to confirm you would like to permanently delete your account.')" id="ui-profile-delete-modal" class="ui-modal ui-modal-delete" data-ui="profile-delete-modal">
        <form id="ui-profile-delete-form" wire:submit="deleteUser" class="ui-form ui-form-profile-delete space-y-4" data-ui="profile-delete-form">
            <x-password
                wire:model="password"
                label="{{ __('Password') }}"
                name="password"
                error-field="password"
                placeholder="{{ __('Password') }}" class="ui-field ui-field-password" data-ui="profile-delete-password"
            />

            <div class="modal-action">
                <x-button id="ui-profile-delete-cancel" type="button" class="btn-ghost ui-action ui-action-cancel" wire:click="$set('confirmingUserDeletion', false)" data-ui="profile-delete-cancel">
                    {{ __('Cancel') }}
                </x-button>
                <x-button id="ui-profile-delete-submit" type="submit" class="btn-error ui-action ui-action-submit-delete" data-ui="profile-delete-submit">
                    {{ __('Delete Account') }}
                </x-button>
            </div>
        </form>
    </x-modal>
</section>
