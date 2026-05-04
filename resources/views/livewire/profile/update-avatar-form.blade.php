<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component
{
    public string $avatar_bg_color = '#1d4ed8';

    public string $avatar_text_color = '#ffffff';

    public function mount(): void
    {
        $user = Auth::user();
        $this->avatar_bg_color = $user->profile?->avatar_bg_color ?? '#1d4ed8';
        $this->avatar_text_color = $user->profile?->avatar_text_color ?? '#ffffff';
    }

    public function updateAvatar(): void
    {
        $validated = $this->validate([
            'avatar_bg_color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'avatar_text_color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        $user = Auth::user();
        $profile = $user->profile()->firstOrCreate();
        $profile->avatar_bg_color = $validated['avatar_bg_color'];
        $profile->avatar_text_color = $validated['avatar_text_color'];
        $profile->save();

        $this->dispatch('profile-avatar-updated');
    }
}; ?>

<section id="ui-profile-avatar-section" class="ui-profile-section ui-profile-avatar" data-ui="profile-avatar-section">
    <form id="ui-profile-avatar-form" wire:submit="updateAvatar" class="ui-form ui-form-profile-avatar space-y-4" data-ui="profile-avatar-form">
        <x-colorpicker wire:model.live="avatar_bg_color" label="{{ __('Avatar background color') }}" name="avatar_bg_color" error-field="avatar_bg_color" required />
        <x-colorpicker wire:model.live="avatar_text_color" label="{{ __('Avatar text color') }}" name="avatar_text_color" error-field="avatar_text_color" required />

        <div class="flex items-center gap-4">
            <x-button class="btn-primary" type="submit">{{ __('Save') }}</x-button>
            <x-action-message class="me-3" on="profile-avatar-updated">{{ __('Saved.') }}</x-action-message>
        </div>
    </form>
</section>
