<?php

use App\Actions\Profile\SwitchEmailToProvider;
use App\Enums\AvatarSource;
use App\Livewire\Profile\Concerns\ReportsProfileTabValidation;
use App\Support\Profile\ProviderEmailOptions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Js;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;

new class extends Component
{
    use ReportsProfileTabValidation;

    public string $email = '';

    public string $facebook_id = '';

    public string $google_id = '';

    public string $discord_id = '';

    public bool $show_contact_email = false;

    public bool $show_contact_facebook = true;

    public bool $show_contact_google = true;

    public bool $show_contact_discord = true;

    public ?string $selected_provider_email = null;

    public string $selected_email = '';

    public bool $confirmingProviderEmailSwitch = false;

    public function mount(): void
    {
        $user = Auth::user();
        $this->email = $user->email;
        $this->facebook_id = (string) ($user->profile?->facebook_id ?? '');
        $this->google_id = (string) ($user->profile?->google_id ?? '');
        $this->discord_id = (string) ($user->profile?->discord_id ?? '');
        $this->show_contact_email = (bool) ($user->profile?->show_contact_email ?? false);
        $this->show_contact_facebook = (bool) ($user->profile?->show_contact_facebook ?? true);
        $this->show_contact_google = (bool) ($user->profile?->show_contact_google ?? true);
        $this->show_contact_discord = (bool) ($user->profile?->show_contact_discord ?? true);
        $this->selected_email = $user->email;
    }

    /**
     * @return list<array{id: string, name: string}>
     */
    public function providerEmailOptions(): array
    {
        return ProviderEmailOptions::for(Auth::user());
    }

    public function openProviderEmailSwitchModal(): void
    {
        if (! filled($this->selected_provider_email)) {
            return;
        }

        $this->confirmingProviderEmailSwitch = true;
    }

    public function updatedSelectedEmail(string $value): void
    {
        $selected = trim($value);
        if ($selected === '' || strcasecmp($selected, $this->email) === 0) {
            $this->selected_email = $this->email;
            $this->selected_provider_email = null;
            $this->confirmingProviderEmailSwitch = false;

            return;
        }

        $this->selected_provider_email = strtolower($selected);
        $this->openProviderEmailSwitchModal();
    }

    public function switchEmailFromProvider(): void
    {
        $this->reportProfileTabValidation('contact', function (): void {
            $availableEmails = ProviderEmailOptions::availableEmails(Auth::user());

            $this->validate([
                'selected_provider_email' => ['required', Rule::in($availableEmails)],
            ]);

            $user = Auth::user();
            $email = (string) $this->selected_provider_email;

            app(SwitchEmailToProvider::class)($user, $email);

            $user->refresh();
            $this->email = $user->email;
            $this->selected_email = $user->email;
            $this->selected_provider_email = null;
            $this->confirmingProviderEmailSwitch = false;

            $this->dispatch('profile-contact-updated');
            $this->toastProviderEmailSwitched($user->email);
        });
    }

    protected function toastProviderEmailSwitched(string $email): void
    {
        $this->js('window.toast('.Js::from([
            'toast' => [
                'type' => 'success',
                'title' => __('ui.profile.use_provider_email_success', [
                    'email' => $email,
                ]),
                'description' => '',
                'icon' => '',
                'css' => 'alert-success',
                'timeout' => 4000,
                'noProgress' => false,
            ],
        ]).')');
    }

    public function unlinkGoogle(): void
    {
        $user = Auth::user();
        $profile = $user->profile;

        if ($profile === null || $profile->google_id === null || $profile->google_id === '') {
            return;
        }

        $avatarSourceChanged = $profile->avatar_source === AvatarSource::Google;

        $profile->google_id = null;
        $profile->google_avatar_url = null;
        $profile->google_email = null;

        if ($avatarSourceChanged) {
            $profile->avatar_source = AvatarSource::Generated;
            $this->deleteStoredAvatarIfPresent($user->id);
            $profile->avatar_path = null;
            $profile->avatar_cache_signature = null;
        }

        $profile->save();

        $this->google_id = '';

        $this->dispatch('profile-contact-updated');

        if ($avatarSourceChanged) {
            $user = $user->fresh(['profile']);
            $url = $user->avatarUrl();
            $version = $user->profile?->updated_at?->getTimestamp() ?? time();
            $separator = str_contains($url, '?') ? '&' : '?';

            $this->dispatch('profile-avatar-updated', avatarUrl: $url.$separator.'v='.$version);
        }
    }

    public function unlinkFacebook(): void
    {
        $user = Auth::user();
        $profile = $user->profile;

        if ($profile === null || $profile->facebook_id === null || $profile->facebook_id === '') {
            return;
        }

        $avatarSourceChanged = $profile->avatar_source === AvatarSource::Facebook;

        $profile->facebook_id = null;
        $profile->facebook_avatar_url = null;
        $profile->facebook_email = null;

        if ($avatarSourceChanged) {
            $profile->avatar_source = AvatarSource::Generated;
            $this->deleteStoredAvatarIfPresent($user->id);
            $profile->avatar_path = null;
            $profile->avatar_cache_signature = null;
        }

        $profile->save();

        $this->facebook_id = '';

        $this->dispatch('profile-contact-updated');

        if ($avatarSourceChanged) {
            $user = $user->fresh(['profile']);
            $url = $user->avatarUrl();
            $version = $user->profile?->updated_at?->getTimestamp() ?? time();
            $separator = str_contains($url, '?') ? '&' : '?';

            $this->dispatch('profile-avatar-updated', avatarUrl: $url.$separator.'v='.$version);
        }
    }

    public function unlinkDiscord(): void
    {
        $user = Auth::user();
        $profile = $user->profile;

        if ($profile === null || $profile->discord_id === null || $profile->discord_id === '') {
            return;
        }

        $avatarSourceChanged = $profile->avatar_source === AvatarSource::Discord;

        $profile->discord_id = null;
        $profile->discord_avatar_url = null;
        $profile->discord_email = null;

        if ($avatarSourceChanged) {
            $profile->avatar_source = AvatarSource::Generated;
            $this->deleteStoredAvatarIfPresent($user->id);
            $profile->avatar_path = null;
            $profile->avatar_cache_signature = null;
        }

        $profile->save();

        $this->discord_id = '';

        $this->dispatch('profile-contact-updated');

        if ($avatarSourceChanged) {
            $user = $user->fresh(['profile']);
            $url = $user->avatarUrl();
            $version = $user->profile?->updated_at?->getTimestamp() ?? time();
            $separator = str_contains($url, '?') ? '&' : '?';

            $this->dispatch('profile-avatar-updated', avatarUrl: $url.$separator.'v='.$version);
        }
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

    private function deleteStoredAvatarIfPresent(int $userId): void
    {
        $path = 'avatars/'.$userId.'.webp';
        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    /**
     * @return list<array{id: string, name: string}>
     */
    public function accountEmailOptions(): array
    {
        $currentEmail = strtolower((string) Auth::user()->email);
        $options = [['id' => $currentEmail, 'name' => $currentEmail]];

        foreach ($this->providerEmailOptions() as $option) {
            $options[] = $option;
        }

        return $options;
    }

    public function hasAlternativeEmailOptions(): bool
    {
        return count($this->providerEmailOptions()) > 0;
    }

    public function updatedShowContactEmail(bool $value): void
    {
        $this->persistVisibilityToggle('show_contact_email', $value);
    }

    public function updatedShowContactFacebook(bool $value): void
    {
        $this->persistVisibilityToggle('show_contact_facebook', $value);
    }

    public function updatedShowContactGoogle(bool $value): void
    {
        $this->persistVisibilityToggle('show_contact_google', $value);
    }

    public function updatedShowContactDiscord(bool $value): void
    {
        $this->persistVisibilityToggle('show_contact_discord', $value);
    }

    private function persistVisibilityToggle(string $column, bool $value): void
    {
        $user = Auth::user();
        $profile = $user->profile()->firstOrCreate();
        $profile->{$column} = $value;
        $profile->save();
        $user->setRelation('profile', $profile);

        $this->dispatch('profile-contact-visibility-updated');
    }
}; ?>

<section id="ui-profile-contact-section" class="ui-profile-section ui-profile-contact" data-ui="profile-contact-section">
    <x-ui.form-errors :title="__('ui.status.oops')" :description="__('ui.status.fix_errors')" icon="o-face-frown" class="!mx-0 mb-4" />
    <div id="ui-profile-contact-form" class="ui-form ui-form-profile-contact space-y-4" data-ui="profile-contact-form">
        <div class="rounded-lg border border-base-200 bg-base-200/40 p-6 space-y-4" data-ui="profile-contact-email-row">
            <div class="flex items-center justify-between gap-4">
                <x-select
                    wire:model.live="selected_email"
                    label="{{ __('ui.profile.use_provider_email_label') }}"
                    :options="$this->accountEmailOptions()"
                    :placeholder="__('ui.profile.use_provider_email_placeholder')"
                    placeholder-value=""
                    error-field="selected_provider_email"
                    :disabled="! $this->hasAlternativeEmailOptions()"
                    inline
                />
                <x-toggle
                    id="show_contact_email"
                    wire:model.live="show_contact_email"
                    :label="__('ui.profile.contact_visibility_toggle_short')"
                    right
                />
            </div>
            <p class="text-xs text-base-content/70">{{ __('ui.profile.use_provider_email_hint') }}</p>
        </div>

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
    </div>

    <div class="flex items-center justify-end gap-4">
        <x-action-message class="me-3" on="profile-contact-visibility-updated">{{ __('ui.common.saved') }}</x-action-message>
    </div>

    @if (count($this->providerEmailOptions()) > 0)
        <x-modal
            wire:model="confirmingProviderEmailSwitch"
            :title="__('ui.profile.use_provider_email_modal_title')"
            :subtitle="filled($selected_provider_email) ? __('ui.profile.use_provider_email_confirm', ['email' => $selected_provider_email]) : null"
            class="backdrop-blur ui-modal ui-modal-provider-email"
            box-class="ui-modal-surface"
            separator
            data-ui="profile-provider-email-modal"
        >
            <div class="modal-action">
                <x-button
                    type="button"
                    class="btn-ghost"
                    wire:click="$set('confirmingProviderEmailSwitch', false)"
                    data-ui="profile-provider-email-cancel"
                >
                    {{ __('ui.common.cancel') }}
                </x-button>
                <x-button
                    type="button"
                    class="btn-primary"
                    wire:click="switchEmailFromProvider"
                    data-ui="profile-provider-email-confirm"
                >
                    {{ __('ui.profile.use_provider_email_apply') }}
                </x-button>
            </div>
        </x-modal>
    @endif

    <fieldset class="fieldset mt-8 py-0" data-ui="profile-integrations">
        <div class="space-y-4">
            <div class="rounded-lg border border-base-200 bg-base-200/40 p-6" data-ui="profile-integration-google">
                @if ($google_id !== '')
                    <div class="grid grid-cols-2 gap-4">
                        <x-input
                            wire:model="google_id"
                            label="{{ __('ui.profile.integrations_google_id_label') }}"
                            placeholder="{{ __('ui.profile.integrations_google_id_label') }}"
                            type="text"
                            name="google_id"
                            inline
                            readonly
                            disabled
                        />
                        <x-button
                            type="button"
                            class="btn-outline btn-error w-full max-w-sm"
                            wire:click="unlinkGoogle"
                            wire:confirm="{{ __('ui.profile.integrations_google_unlink_confirm') }}"
                        >{{ __('ui.profile.integrations_google_unlink') }}</x-button>
                    </div>
                    <div class="mt-3">
                        <x-toggle
                            id="show_contact_google"
                            wire:model.live="show_contact_google"
                            :label="__('ui.profile.contact_visibility_toggle_google')"
                            right
                        />
                    </div>
                @elseif (config('services.google.client_id'))
                    <div class="flex flex-col gap-4">
                        <p class="text-sm text-base-content/80">{{ __('ui.profile.integrations_google_link_hint') }}</p>
                        {{-- OAuth must use full document navigation; wire:navigate would fetch redirect → CORS on accounts.google.com --}}
                        <x-button :link="route('google.redirect', ['return_tab' => 'contact'])" :no-wire-navigate="true" class="btn-primary btn-lg min-h-14 w-full max-w-sm px-8 text-base font-semibold">{{ __('ui.profile.avatar_link_google') }}</x-button>
                    </div>
                @endif
            </div>

            <div class="rounded-lg border border-base-200 bg-base-200/40 p-6" data-ui="profile-integration-facebook">
            @if ($facebook_id !== '')
                <div class="grid grid-cols-2 gap-4">
                    <x-input
                        wire:model="facebook_id"
                        label="{{ __('ui.profile.integrations_facebook_id_label') }}"
                        placeholder="{{ __('ui.profile.integrations_facebook_id_label') }}"
                        type="text"
                        name="facebook_id"
                        inline
                        readonly
                        disabled
                    />
                    <x-button
                        type="button"
                        class="btn-outline btn-error w-full max-w-sm"
                        wire:click="unlinkFacebook"
                        wire:confirm="{{ __('ui.profile.integrations_facebook_unlink_confirm') }}"
                    >{{ __('ui.profile.integrations_facebook_unlink') }}</x-button>
                </div>
                <div class="mt-3">
                    <x-toggle
                        id="show_contact_facebook"
                        wire:model.live="show_contact_facebook"
                        :label="__('ui.profile.contact_visibility_toggle_facebook')"
                        right
                    />
                </div>
            @elseif (config('services.facebook.client_id'))
                <div class="flex flex-col gap-4">
                    <p class="text-sm text-base-content/80">{{ __('ui.profile.integrations_facebook_link_hint') }}</p>
                    {{-- OAuth must use full document navigation; wire:navigate would fetch redirect → CORS on facebook.com --}}
                    <x-button :link="route('facebook.redirect', ['return_tab' => 'contact'])" :no-wire-navigate="true" class="btn-primary btn-lg min-h-14 w-full max-w-sm px-8 text-base font-semibold">{{ __('ui.profile.avatar_link_facebook') }}</x-button>
                </div>
            @endif
        </div>

            <div class="rounded-lg border border-base-200 bg-base-200/40 p-6" data-ui="profile-integration-discord">
                @if ($discord_id !== '')
                    <div class="grid grid-cols-2 gap-4">
                        <x-input
                            wire:model="discord_id"
                            label="{{ __('ui.profile.integrations_discord_id_label') }}"
                            placeholder="{{ __('ui.profile.integrations_discord_id_label') }}"
                            type="text"
                            name="discord_id"
                            inline
                            readonly
                            disabled
                        />
                        <x-button
                            type="button"
                            class="btn-outline btn-error w-full max-w-sm"
                            wire:click="unlinkDiscord"
                            wire:confirm="{{ __('ui.profile.integrations_discord_unlink_confirm') }}"
                        >{{ __('ui.profile.integrations_discord_unlink') }}</x-button>
                    </div>
                    <div class="mt-3">
                        <x-toggle
                            id="show_contact_discord"
                            wire:model.live="show_contact_discord"
                            :label="__('ui.profile.contact_visibility_toggle_discord')"
                            right
                        />
                    </div>
                @elseif (config('services.discord.client_id'))
                    <div class="flex flex-col gap-4">
                        <p class="text-sm text-base-content/80">{{ __('ui.profile.integrations_discord_link_hint') }}</p>
                        {{-- OAuth must use full document navigation; wire:navigate would fetch redirect → CORS on discord.com --}}
                        <x-button :link="route('discord.redirect', ['return_tab' => 'contact'])" :no-wire-navigate="true" class="btn-primary btn-lg min-h-14 w-full max-w-sm px-8 text-base font-semibold">{{ __('ui.profile.avatar_link_discord') }}</x-button>
                    </div>
                @endif
            </div>
        </div>
    </fieldset>
</section>
