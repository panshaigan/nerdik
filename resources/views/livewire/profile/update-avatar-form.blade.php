<?php

use App\Actions\Avatars\RefreshCachedAvatar;
use App\Actions\Avatars\StoreUploadedAvatar;
use App\Enums\AvatarSource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Features\SupportFileUploads\WithFileUploads;
use Livewire\Volt\Component;

new class extends Component
{
    use WithFileUploads;

    public string $avatar_source = 'generated';

    public string $avatar_bg_color = '#1d4ed8';

    public string $avatar_text_color = '#ffffff';

    public string $avatar_initials = '';

    /** @var mixed */
    public $croppedAvatar = null;

    public string $userEmail = '';

    public function mount(): void
    {
        $user = Auth::user();
        $profile = $user->profile;
        $this->userEmail = (string) $user->email;
        $this->avatar_bg_color = $profile?->avatar_bg_color ?? '#1d4ed8';
        $this->avatar_text_color = $profile?->avatar_text_color ?? '#ffffff';
        $this->avatar_initials = $profile?->avatar_initials ?? '';
        $src = $profile?->avatar_source;
        if ($src instanceof AvatarSource) {
            $this->avatar_source = $src->value;
        } elseif (is_string($src) && $src !== '') {
            $this->avatar_source = $src;
        } else {
            $this->avatar_source = AvatarSource::Generated->value;
        }
    }

    public function updatedAvatarSource(string $value): void
    {
        if ($value !== 'uploaded') {
            $this->reset('croppedAvatar');
        }
    }

    public function clearCroppedAvatar(): void
    {
        $this->reset('croppedAvatar');
    }

    public function refreshRemoteAvatar(): void
    {
        $user = Auth::user();
        $source = AvatarSource::tryFrom($this->avatar_source);
        if ($source === null || ! $source->usesRemoteCache()) {
            return;
        }

        try {
            app(RefreshCachedAvatar::class)($user, $source);
            $this->dispatchProfileAvatarUpdated();
        } catch (\Throwable $e) {
            $this->addError('avatar', $e->getMessage());
        }
    }

    public function updateAvatar(): void
    {
        $user = Auth::user();
        $uploadedPath = 'avatars/'.$user->id.'.webp';
        $hasExistingUpload = Storage::disk('public')->exists($uploadedPath);
        $rawPrev = $user->profile?->avatar_source;
        $previousSource = $rawPrev instanceof AvatarSource ? $rawPrev->value : (string) ($rawPrev ?? AvatarSource::Generated->value);

        $validated = $this->validate([
            'avatar_source' => ['required', 'string', Rule::in(array_map(static fn (AvatarSource $s) => $s->value, AvatarSource::cases()))],
            'avatar_bg_color' => ['required_if:avatar_source,generated', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'avatar_text_color' => ['required_if:avatar_source,generated', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'avatar_initials' => ['nullable', 'string', 'max:3', 'regex:/^[A-Za-z]{1,3}$/'],
            'croppedAvatar' => [
                Rule::requiredIf(fn (): bool => $this->avatar_source === 'uploaded'
                    && ($previousSource !== AvatarSource::Uploaded->value || ! $hasExistingUpload)),
                'nullable',
                'image',
                'max:5120',
                'mimes:jpeg,jpg,png,webp',
            ],
        ]);

        $source = AvatarSource::from($validated['avatar_source']);
        $profile = $user->profile()->firstOrCreate();

        if ($source === AvatarSource::Generated) {
            $profile->avatar_source = AvatarSource::Generated;
            $profile->avatar_bg_color = $validated['avatar_bg_color'];
            $profile->avatar_text_color = $validated['avatar_text_color'];
            $rawInitials = trim((string) ($validated['avatar_initials'] ?? ''));
            $profile->avatar_initials = $rawInitials !== '' ? strtoupper($rawInitials) : null;
            $this->deleteStoredAvatarIfPresent($user->id);
            $profile->avatar_path = null;
            $profile->avatar_cache_signature = null;
            $profile->save();
            $this->dispatchProfileAvatarUpdated();

            return;
        }

        if ($source === AvatarSource::Uploaded) {
            if ($this->croppedAvatar !== null) {
                $path = app(StoreUploadedAvatar::class)($user, $this->croppedAvatar);
                $profile->avatar_path = $path;
                $profile->avatar_cache_signature = null;
            }
            $profile->avatar_source = AvatarSource::Uploaded;
            $profile->avatar_bg_color = $validated['avatar_bg_color'] ?? $profile->avatar_bg_color;
            $profile->avatar_text_color = $validated['avatar_text_color'] ?? $profile->avatar_text_color;
            $profile->save();
            $this->reset('croppedAvatar');
            $this->dispatchProfileAvatarUpdated();

            return;
        }

        if ($source === AvatarSource::Gravatar) {
            $profile->avatar_source = AvatarSource::Gravatar;
            $profile->save();
            try {
                app(RefreshCachedAvatar::class)($user->fresh(), AvatarSource::Gravatar);
            } catch (\Throwable $e) {
                $this->addError('avatar', $e->getMessage());

                return;
            }
            $this->dispatchProfileAvatarUpdated();

            return;
        }

        if ($source === AvatarSource::Google) {
            if ($profile->google_id === null || $profile->google_id === '') {
                $this->addError('avatar_source', __('Link your Google account first using the button below.'));

                return;
            }
            $profile->avatar_source = AvatarSource::Google;
            $profile->save();
            try {
                app(RefreshCachedAvatar::class)($user->fresh(), AvatarSource::Google);
            } catch (\Throwable $e) {
                $this->addError('avatar', $e->getMessage());

                return;
            }
            $this->dispatchProfileAvatarUpdated();

            return;
        }

        if ($source === AvatarSource::Facebook) {
            if ($profile->facebook_id === null || $profile->facebook_id === '') {
                $this->addError('avatar_source', __('Link your Facebook account first using the button below.'));

                return;
            }
            $profile->avatar_source = AvatarSource::Facebook;
            $profile->save();
            try {
                app(RefreshCachedAvatar::class)($user->fresh(), AvatarSource::Facebook);
            } catch (\Throwable $e) {
                $this->addError('avatar', $e->getMessage());

                return;
            }
            $this->dispatchProfileAvatarUpdated();
        }
    }

    private function dispatchProfileAvatarUpdated(): void
    {
        $user = Auth::user()->fresh(['profile']);
        $url = $user->avatarUrl();
        $version = $user->profile?->updated_at?->getTimestamp() ?? time();

        $this->dispatch('profile-avatar-updated', avatarUrl: $this->cacheBustedAvatarUrl($url, $version));
    }

    private function cacheBustedAvatarUrl(string $url, int $version): string
    {
        $separator = str_contains($url, '?') ? '&' : '?';

        return $url.$separator.'v='.$version;
    }

    private function deleteStoredAvatarIfPresent(int $userId): void
    {
        $path = 'avatars/'.$userId.'.webp';
        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}; ?>

<section id="ui-profile-avatar-section" class="ui-profile-section ui-profile-avatar" data-ui="profile-avatar-section">
    <form id="ui-profile-avatar-form" wire:submit="updateAvatar" class="ui-form ui-form-profile-avatar space-y-6" data-ui="profile-avatar-form">
        <x-field-error :messages="$errors->get('avatar')" class="mt-2" />
        <x-field-error :messages="$errors->get('avatar_source')" class="mt-2" />

        <fieldset class="fieldset py-0">
            <legend class="fieldset-legend mb-2">{{ __('ui.profile.avatar_source') }}</legend>
            <div class="grid gap-3 sm:grid-cols-2">
                <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-base-300 p-3 has-[:checked]:border-primary has-[:checked]:bg-primary/5">
                    <input type="radio" wire:model.live="avatar_source" name="avatar_source" value="generated" class="radio radio-primary mt-0.5" />
                    <span>
                        <span class="block text-sm font-semibold text-base-content">{{ __('ui.profile.avatar_generated') }}</span>
                        <span class="mt-0.5 block text-xs text-base-content/70">{{ __('ui.profile.avatar_generated_hint') }}</span>
                    </span>
                </label>
                <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-base-300 p-3 has-[:checked]:border-primary has-[:checked]:bg-primary/5">
                    <input type="radio" wire:model.live="avatar_source" name="avatar_source" value="uploaded" class="radio radio-primary mt-0.5" />
                    <span>
                        <span class="block text-sm font-semibold text-base-content">{{ __('ui.profile.avatar_uploaded') }}</span>
                        <span class="mt-0.5 block text-xs text-base-content/70">{{ __('ui.profile.avatar_uploaded_hint') }}</span>
                    </span>
                </label>
                <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-base-300 p-3 has-[:checked]:border-primary has-[:checked]:bg-primary/5">
                    <input type="radio" wire:model.live="avatar_source" name="avatar_source" value="gravatar" class="radio radio-primary mt-0.5" />
                    <span>
                        <span class="block text-sm font-semibold text-base-content">{{ __('ui.profile.avatar_gravatar') }}</span>
                        <span class="mt-0.5 block text-xs text-base-content/70">{{ __('ui.profile.avatar_gravatar_hint') }}</span>
                    </span>
                </label>
                <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-base-300 p-3 has-[:checked]:border-primary has-[:checked]:bg-primary/5">
                    <input type="radio" wire:model.live="avatar_source" name="avatar_source" value="google" class="radio radio-primary mt-0.5" />
                    <span>
                        <span class="block text-sm font-semibold text-base-content">{{ __('ui.profile.avatar_google') }}</span>
                        <span class="mt-0.5 block text-xs text-base-content/70">{{ __('ui.profile.avatar_google_hint') }}</span>
                    </span>
                </label>
                <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-base-300 p-3 has-[:checked]:border-primary has-[:checked]:bg-primary/5 sm:col-span-2">
                    <input type="radio" wire:model.live="avatar_source" name="avatar_source" value="facebook" class="radio radio-primary mt-0.5" />
                    <span>
                        <span class="block text-sm font-semibold text-base-content">{{ __('ui.profile.avatar_facebook') }}</span>
                        <span class="mt-0.5 block text-xs text-base-content/70">{{ __('ui.profile.avatar_facebook_hint') }}</span>
                    </span>
                </label>
            </div>
        </fieldset>

        @if ($avatar_source === 'generated')
            <div class="ui-profile-avatar-source-panel grid gap-6 rounded-lg border border-base-200 bg-base-200/40 p-6 md:grid-cols-2 md:items-center md:gap-8">
                <div class="flex flex-col gap-4">
                    <p class="text-sm text-base-content/80">{{ __('ui.profile.avatar_colors_hint') }}</p>
                    <x-input
                        wire:model.live="avatar_initials"
                        label="{{ __('ui.profile.avatar_initials') }}"
                        type="text"
                        name="avatar_initials"
                        error-field="avatar_initials"
                        placeholder="{{ auth()->user()->displayName() }}"
                        maxlength="3"
                    />
                    <p class="text-xs text-base-content/70">{{ __('ui.profile.avatar_initials_hint') }}</p>
                    <x-colorpicker wire:model.live="avatar_bg_color" label="{{ __('ui.profile.avatar_bg_color') }}" name="avatar_bg_color" error-field="avatar_bg_color" required />
                    <x-colorpicker wire:model.live="avatar_text_color" label="{{ __('ui.profile.avatar_text_color') }}" name="avatar_text_color" error-field="avatar_text_color" required />
                </div>
                <div class="flex flex-col items-center justify-center gap-3">
                    <span class="text-sm font-medium text-base-content/80">{{ __('ui.common.preview') }}</span>
                    @php
                        $previewInitials = trim($avatar_initials);
                        $previewName = $previewInitials !== '' ? $previewInitials : auth()->user()->displayName();
                        $previewLength = $previewInitials !== '' ? strlen($previewInitials) : 2;
                    @endphp
                    <img
                        src="{{ \App\Models\User::uiAvatarsUrl($previewName, $avatar_bg_color, $avatar_text_color, $previewLength) }}"
                        alt=""
                        class="h-48 w-48 rounded-full object-cover ring-2 ring-base-300/50 sm:h-56 sm:w-56"
                        loading="lazy"
                    />
                </div>
            </div>
        @endif

        @if ($avatar_source === 'uploaded')
            <x-image-crop-upload
                aspect="square"
                wire-property="croppedAvatar"
                clear-method="clearCroppedAvatar"
                error-field="croppedAvatar"
                form-selector="#ui-profile-avatar-form"
                file-input-id="ui-profile-avatar-file"
                :preview-url="auth()->user()->avatarUrl()"
                output-size="512,512"
                file-name="avatar.webp"
                :modal-title="__('ui.profile.crop_avatar')"
            />
        @endif

        @if ($avatar_source === 'gravatar')
            <div class="ui-profile-avatar-source-panel grid gap-6 rounded-lg border border-base-200 bg-base-200/40 p-6 md:grid-cols-2 md:items-center md:gap-8">
                <div class="flex flex-col gap-4">
                    <p class="text-sm text-base-content/80">{{ __('ui.profile.avatar_gravatar_cache_hint') }}</p>
                    <x-button type="button" class="btn-outline btn-md w-full max-w-sm" wire:click="refreshRemoteAvatar">{{ __('ui.profile.avatar_refresh_gravatar') }}</x-button>
                </div>
                <div class="flex flex-col items-center justify-center gap-3">
                    <span class="text-sm font-medium text-base-content/80">{{ __('ui.common.preview') }}</span>
                    <img
                        src="https://www.gravatar.com/avatar/{{ md5(strtolower(trim($userEmail))) }}?s=320&d=mp"
                        alt=""
                        class="h-48 w-48 rounded-full object-cover ring-2 ring-base-300/50 sm:h-56 sm:w-56"
                        loading="lazy"
                    />
                </div>
            </div>
        @endif

        @if ($avatar_source === 'google')
            <div class="ui-profile-avatar-source-panel grid gap-6 rounded-lg border border-base-200 bg-base-200/40 p-6 md:grid-cols-2 md:items-center md:gap-8">
                <div class="flex flex-col gap-4">
                    @if (auth()->user()->profile?->google_id)
                        <p class="text-sm text-base-content/80">{{ __('ui.profile.avatar_google_linked_hint') }}</p>
                        <x-button type="button" class="btn-outline btn-md w-full max-w-sm" wire:click="refreshRemoteAvatar">{{ __('ui.profile.avatar_refresh_google') }}</x-button>
                    @else
                        <p class="text-sm text-base-content/80">{{ __('ui.profile.avatar_google_link_hint') }}</p>
                        <a href="{{ route('google.redirect', ['return_tab' => 'avatar']) }}" class="btn btn-primary btn-lg min-h-14 w-full max-w-sm px-8 text-base font-semibold">{{ __('ui.profile.avatar_link_google') }}</a>
                    @endif
                </div>
                <div class="flex flex-col items-center justify-center gap-3">
                    <span class="text-sm font-medium text-base-content/80">{{ auth()->user()->profile?->google_id ? __('ui.profile.avatar_current') : __('ui.common.preview') }}</span>
                    <img
                        src="{{ auth()->user()->avatarUrl() }}"
                        alt=""
                        class="h-48 w-48 rounded-full object-cover ring-2 ring-base-300/50 sm:h-56 sm:w-56"
                        loading="lazy"
                    />
                </div>
            </div>
        @endif

        @if ($avatar_source === 'facebook')
            <div class="ui-profile-avatar-source-panel grid gap-6 rounded-lg border border-base-200 bg-base-200/40 p-6 md:grid-cols-2 md:items-center md:gap-8">
                <div class="flex flex-col gap-4">
                    @if (auth()->user()->profile?->facebook_id)
                        <p class="text-sm text-base-content/80">{{ __('ui.profile.avatar_facebook_linked_hint') }}</p>
                        <x-button type="button" class="btn-outline btn-md w-full max-w-sm" wire:click="refreshRemoteAvatar">{{ __('ui.profile.avatar_refresh_facebook') }}</x-button>
                    @else
                        <p class="text-sm text-base-content/80">{{ __('ui.profile.avatar_facebook_link_hint') }}</p>
                        <a href="{{ route('facebook.redirect', ['return_tab' => 'avatar']) }}" class="btn btn-primary btn-lg min-h-14 w-full max-w-sm px-8 text-base font-semibold">{{ __('ui.profile.avatar_link_facebook') }}</a>
                    @endif
                </div>
                <div class="flex flex-col items-center justify-center gap-3">
                    <span class="text-sm font-medium text-base-content/80">{{ auth()->user()->profile?->facebook_id ? __('ui.profile.avatar_current') : __('ui.common.preview') }}</span>
                    <img
                        src="{{ auth()->user()->avatarUrl() }}"
                        alt=""
                        class="h-48 w-48 rounded-full object-cover ring-2 ring-base-300/50 sm:h-56 sm:w-56"
                        loading="lazy"
                    />
                </div>
            </div>
        @endif

        <div class="flex items-center justify-end gap-4">
            <x-action-message class="me-3" on="profile-avatar-updated">{{ __('ui.common.saved') }}</x-action-message>
            <x-button class="btn-primary" type="submit">{{ __('ui.common.save') }}</x-button>
        </div>
    </form>
</section>
