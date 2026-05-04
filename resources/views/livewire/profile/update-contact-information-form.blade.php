<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
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
        $user = Auth::user();
        $validated = $this->validate([
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($user->id)],
            'discord_handle' => ['nullable', 'string', 'max:255'],
        ]);

        $user->email = $validated['email'];
        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }
        $user->save();

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
        <x-input wire:model="email" label="{{ __('Email') }}" type="email" name="email" error-field="email" required />
        <x-input wire:model="discord_handle" label="{{ __('Discord (optional)') }}" type="text" name="discord_handle" error-field="discord_handle" />

        @if (auth()->user() instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! auth()->user()->hasVerifiedEmail())
            <div class="mt-2">
                <p class="text-sm text-base-content/80">
                    {{ __('Your email address is unverified.') }}
                    <x-button type="button" wire:click.prevent="sendVerification" class="btn-link link link-primary h-auto min-h-0 p-0 text-sm font-normal">
                        {{ __('Click here to re-send the verification email.') }}
                    </x-button>
                </p>
                @if (session('status') === 'verification-link-sent')
                    <p class="mt-2 text-sm font-medium text-success">
                        {{ __('A new verification link has been sent to your email address.') }}
                    </p>
                @endif
            </div>
        @endif

        <div class="flex items-center gap-4">
            <x-button class="btn-primary" type="submit">{{ __('Save') }}</x-button>
            <x-action-message class="me-3" on="profile-contact-updated">{{ __('Saved.') }}</x-action-message>
        </div>
    </form>
</section>
