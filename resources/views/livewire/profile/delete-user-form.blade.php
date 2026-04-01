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

<section class="space-y-6">
    <header>
        <h2 class="text-lg font-medium text-base-content">
            {{ __('Delete Account') }}
        </h2>

        <p class="mt-1 text-sm text-base-content/70">
            {{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Before deleting your account, please download any data or information that you wish to retain.') }}
        </p>
    </header>

    <x-button type="button" class="btn-error" wire:click="$set('confirmingUserDeletion', true)">
        {{ __('Delete Account') }}
    </x-button>

    <x-modal wire:model="confirmingUserDeletion" :title="__('Are you sure you want to delete your account?')" :subtitle="__('Once your account is deleted, all of its resources and data will be permanently deleted. Please enter your password to confirm you would like to permanently delete your account.')">
        <form wire:submit="deleteUser" class="space-y-4">
            <x-password
                wire:model="password"
                label="{{ __('Password') }}"
                name="password"
                error-field="password"
                placeholder="{{ __('Password') }}"
            />

            <div class="modal-action">
                <x-button type="button" class="btn-ghost" wire:click="$set('confirmingUserDeletion', false)">
                    {{ __('Cancel') }}
                </x-button>
                <x-button type="submit" class="btn-error">
                    {{ __('Delete Account') }}
                </x-button>
            </div>
        </form>
    </x-modal>
</section>
