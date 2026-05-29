<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Volt\Component;

new class extends Component
{
    public string $email = '';

    public string $discord_handle = '';

    public function mount(): void
    {
        $user = Auth::user();
        $this->email = $user->email;
        $this->discord_handle = $user->profile?->discord_handle ?? '';
    }

    public function updateContactInformation(): void
    {
        $validated = $this->validate([
            'discord_handle' => ['nullable', 'string', 'max:255'],
        ]);

        $user = Auth::user();

        $profile = $user->profile()->firstOrCreate();
        $profile->discord_handle = $validated['discord_handle'] ?: null;
        $profile->save();

        $this->dispatch('profile-contact-updated');
    }

    public function sendVerification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false));

            return;
        }

        $user->sendEmailVerificationNotification();
        Session::flash('status', 'verification-link-sent');
    }
}; ?>

<section id="ui-profile-contact-section" class="ui-profile-section ui-profile-contact" data-ui="profile-contact-section">
    <form id="ui-profile-contact-form" wire:submit="updateContactInformation" class="ui-form ui-form-profile-contact space-y-4" data-ui="profile-contact-form">
        <x-input wire:model="email" label="{{ __('ui.common.email') }}" type="email" name="email" error-field="email" required readonly disabled />
        <x-input wire:model="discord_handle" label="{{ __('ui.profile.discord_optional') }}" type="text" name="discord_handle" error-field="discord_handle" />

        @if (auth()->user() instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! auth()->user()->hasVerifiedEmail())
            <div class="mt-2">
                <p class="text-sm text-base-content/80">
                    {{ __('ui.profile.email_unverified') }}
                    <x-button type="button" wire:click.prevent="sendVerification" class="btn-link link link-primary h-auto min-h-0 p-0 text-sm font-normal">
                        {{ __('ui.profile.resend_verification') }}
                    </x-button>
                </p>
                @if (session('status') === 'verification-link-sent')
                    <p class="mt-2 text-sm font-medium text-success">
                        {{ __('ui.profile.verification_link_sent_contact') }}
                    </p>
                @endif
            </div>
        @endif

        <div class="flex items-center justify-end gap-4">
            <x-action-message class="me-3" on="profile-contact-updated">{{ __('ui.common.saved') }}</x-action-message>
            <x-button class="btn-primary" type="submit">{{ __('ui.common.save') }}</x-button>
        </div>
    </form>
</section>
