<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;

new class extends Component
{
    public string $name = '';

    public string $nickname = '';

    public string $email = '';

    public string $discord_handle = '';

    public string $timezone = '';

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $u = Auth::user();
        $this->name = $u->name ?? '';
        $this->nickname = $u->nickname ?? '';
        $this->email = $u->email;
        $this->discord_handle = $u->discord_handle ?? '';
        $this->timezone = $u->timezone ?? '';
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $validated = $this->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'nickname' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($user->id)],
            'discord_handle' => ['nullable', 'string', 'max:255'],
            'timezone' => ['nullable', 'string', 'timezone'],
        ]);

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        $this->dispatch('profile-updated', name: $user->nickname);
    }

    /**
     * Send an email verification notification to the current user.
     */
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

<section id="ui-profile-information-section" class="ui-profile-section ui-profile-information" data-ui="profile-information-section">
    <header>
        <h2 class="text-lg font-medium text-base-content">
            {{ __('Profile Information') }}
        </h2>

        <p class="mt-1 text-sm text-base-content/70">
            {{ __("Update your account's profile information and email address.") }}
        </p>
    </header>

    <form id="ui-profile-information-form" wire:submit="updateProfileInformation" class="ui-form ui-form-profile-information mt-6 space-y-4" data-ui="profile-information-form">
        <x-input
            wire:model="nickname"
            label="{{ __('Nickname') }}"
            type="text"
            name="nickname"
            error-field="nickname"
            required
            autofocus
            autocomplete="nickname"
            class="ui-field ui-field-nickname"
            data-ui="profile-nickname"
        />

        <x-input
            wire:model="name"
            label="{{ __('Name (optional)') }}"
            type="text"
            name="name"
            error-field="name"
            autocomplete="name"
            class="ui-field ui-field-name"
            data-ui="profile-name"
        />

        <x-input
            wire:model="discord_handle"
            label="{{ __('Discord (optional)') }}"
            type="text"
            name="discord_handle"
            placeholder="username"
            error-field="discord_handle"
            autocomplete="off"
            class="ui-field ui-field-discord"
            data-ui="profile-discord"
        />

        <div>
            <fieldset class="fieldset py-0">
                <legend class="fieldset-legend mb-0.5">{{ __('Timezone (for displaying dates)') }}</legend>
                <select wire:model="timezone" id="timezone" name="timezone" class="select select-bordered w-full ui-field ui-field-timezone" data-ui="profile-timezone">
                    <option value="">{{ __('Use server default (UTC)') }}</option>
                    @if ($timezone && ! in_array($timezone, ['UTC', 'Europe/Warsaw', 'Europe/London', 'Europe/Berlin', 'Europe/Paris', 'America/New_York', 'America/Chicago', 'America/Los_Angeles', 'Asia/Tokyo', 'Australia/Sydney'], true))
                        <option value="{{ $timezone }}" selected>{{ $timezone }}</option>
                    @endif
                    <option value="UTC">UTC</option>
                    <option value="Europe/Warsaw">Europe/Warsaw</option>
                    <option value="Europe/London">Europe/London</option>
                    <option value="Europe/Berlin">Europe/Berlin</option>
                    <option value="Europe/Paris">Europe/Paris</option>
                    <option value="America/New_York">America/New_York</option>
                    <option value="America/Chicago">America/Chicago</option>
                    <option value="America/Los_Angeles">America/Los_Angeles</option>
                    <option value="Asia/Tokyo">Asia/Tokyo</option>
                    <option value="Australia/Sydney">Australia/Sydney</option>
                </select>
            </fieldset>
            <p class="mt-1 text-xs text-base-content/60">{{ __('All times are stored in UTC; your timezone only affects how they are shown.') }}</p>
            <x-field-error :messages="$errors->get('timezone')" class="mt-2" />
        </div>

        <div>
            <x-input
                wire:model="email"
                label="{{ __('Email') }}"
                type="email"
                name="email"
                error-field="email"
                required
                autocomplete="username"
                class="ui-field ui-field-email"
                data-ui="profile-email"
            />

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
        </div>

        <div class="flex items-center gap-4">
            <x-button id="ui-profile-information-submit" class="btn-primary ui-action ui-action-submit" type="submit" data-ui="profile-information-submit">{{ __('Save') }}</x-button>

            <x-action-message class="me-3" on="profile-updated">
                {{ __('Saved.') }}
            </x-action-message>
        </div>
    </form>
</section>
