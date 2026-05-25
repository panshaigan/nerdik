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
            <legend class="fieldset-legend mb-2">{{ __('Avatar source') }}</legend>
            <div class="grid gap-3 sm:grid-cols-2">
                <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-base-300 p-3 has-[:checked]:border-primary has-[:checked]:bg-primary/5">
                    <input type="radio" wire:model.live="avatar_source" name="avatar_source" value="generated" class="radio radio-primary mt-0.5" />
                    <span>
                        <span class="block text-sm font-semibold text-base-content">{{ __('Generated (initials)') }}</span>
                        <span class="mt-0.5 block text-xs text-base-content/70">{{ __('Colors for ui-avatars.com') }}</span>
                    </span>
                </label>
                <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-base-300 p-3 has-[:checked]:border-primary has-[:checked]:bg-primary/5">
                    <input type="radio" wire:model.live="avatar_source" name="avatar_source" value="uploaded" class="radio radio-primary mt-0.5" />
                    <span>
                        <span class="block text-sm font-semibold text-base-content">{{ __('Uploaded photo') }}</span>
                        <span class="mt-0.5 block text-xs text-base-content/70">{{ __('Crop to a square; stored as WebP (max 5 MB upload)') }}</span>
                    </span>
                </label>
                <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-base-300 p-3 has-[:checked]:border-primary has-[:checked]:bg-primary/5">
                    <input type="radio" wire:model.live="avatar_source" name="avatar_source" value="gravatar" class="radio radio-primary mt-0.5" />
                    <span>
                        <span class="block text-sm font-semibold text-base-content">{{ __('Gravatar') }}</span>
                        <span class="mt-0.5 block text-xs text-base-content/70">{{ __('Uses your profile email') }}</span>
                    </span>
                </label>
                <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-base-300 p-3 has-[:checked]:border-primary has-[:checked]:bg-primary/5">
                    <input type="radio" wire:model.live="avatar_source" name="avatar_source" value="google" class="radio radio-primary mt-0.5" />
                    <span>
                        <span class="block text-sm font-semibold text-base-content">{{ __('Google profile photo') }}</span>
                        <span class="mt-0.5 block text-xs text-base-content/70">{{ __('Requires linked Google account') }}</span>
                    </span>
                </label>
                <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-base-300 p-3 has-[:checked]:border-primary has-[:checked]:bg-primary/5 sm:col-span-2">
                    <input type="radio" wire:model.live="avatar_source" name="avatar_source" value="facebook" class="radio radio-primary mt-0.5" />
                    <span>
                        <span class="block text-sm font-semibold text-base-content">{{ __('Facebook profile photo') }}</span>
                        <span class="mt-0.5 block text-xs text-base-content/70">{{ __('Requires linked Facebook account') }}</span>
                    </span>
                </label>
            </div>
        </fieldset>

        @if ($avatar_source === 'generated')
            <div class="space-y-4 rounded-lg border border-base-200 bg-base-200/40 p-4">
                <p class="text-sm text-base-content/80">{{ __('Pick background and text colors for your generated avatar.') }}</p>
                <x-colorpicker wire:model.live="avatar_bg_color" label="{{ __('Avatar background color') }}" name="avatar_bg_color" error-field="avatar_bg_color" required />
                <x-colorpicker wire:model.live="avatar_text_color" label="{{ __('Avatar text color') }}" name="avatar_text_color" error-field="avatar_text_color" required />
                <div class="flex items-center gap-3">
                    <span class="text-sm font-medium text-base-content/80">{{ __('Preview') }}</span>
                    <img
                        src="https://ui-avatars.com/api/?name={{ rawurlencode(auth()->user()->displayName()) }}&background={{ ltrim($avatar_bg_color, '#') }}&color={{ ltrim($avatar_text_color, '#') }}&rounded=true&bold=true"
                        alt=""
                        class="h-14 w-14 rounded-full border border-base-300 object-cover"
                        loading="lazy"
                    />
                </div>
            </div>
        @endif

        @if ($avatar_source === 'uploaded')
            <div
                class="ui-profile-avatar-dropzone grid gap-6 rounded-lg border border-base-200 bg-base-200/40 p-6 md:grid-cols-2 md:items-center md:gap-8"
                data-profile-avatar-dropzone
                data-label-choose="{{ __('Choose file') }}"
                data-label-crop="{{ __('Crop file') }}"
                data-label-remove="{{ __('Remove image') }}"
            >
                <div class="flex flex-col gap-4">
                    <input
                        type="file"
                        id="ui-profile-avatar-file"
                        accept="image/jpeg,image/png,image/webp"
                        class="sr-only"
                        data-profile-avatar-file
                    />
                    <label
                        for="ui-profile-avatar-file"
                        class="ui-profile-avatar-dropzone-upload flex min-h-64 cursor-pointer flex-col items-center justify-center gap-4 rounded-xl border-2 border-dashed border-base-300/80 bg-base-100/30 p-8 text-center"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-10 text-base-content/50" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909M3.75 21h16.5A2.25 2.25 0 0 0 22.5 18.75V5.25A2.25 2.25 0 0 0 20.25 3H3.75A2.25 2.25 0 0 0 1.5 5.25v13.5A2.25 2.25 0 0 0 3.75 21Z" />
                        </svg>
                        <span class="text-base font-semibold text-base-content">{{ __('Upload a photo') }}</span>
                        <p class="max-w-sm text-sm text-base-content/70">
                            {{ __('Drag and drop an image here, or use the button below. JPEG, PNG, or WebP. After cropping, click Save below.') }}
                        </p>
                        <span
                            class="btn btn-primary btn-lg min-h-14 w-full max-w-sm px-8 text-base font-semibold"
                            data-profile-avatar-file-trigger
                        >
                            <span data-profile-avatar-file-button-text>{{ __('Choose file') }}</span>
                        </span>
                        <p class="hidden max-w-sm text-sm text-base-content/70" data-profile-avatar-recrop-hint>
                            {{ __('Click Crop file to adjust the crop before saving.') }}
                        </p>
                    </label>
                    <x-button
                        type="button"
                        class="btn-outline btn-md mx-auto hidden w-full max-w-sm"
                        data-profile-avatar-remove
                    >
                        {{ __('Remove image') }}
                    </x-button>
                    <x-field-error :messages="$errors->get('croppedAvatar')" class="mt-2" />
                </div>

                <div wire:ignore class="flex flex-col items-center justify-center gap-3">
                    <img
                        src="{{ auth()->user()->avatarUrl() }}"
                        alt=""
                        class="h-48 w-48 rounded-full object-cover ring-2 ring-base-300/50 sm:h-56 sm:w-56"
                        loading="lazy"
                        data-profile-avatar-preview
                        data-default-src="{{ auth()->user()->avatarUrl() }}"
                    />
                </div>
            </div>
        @endif

        @if ($avatar_source === 'gravatar')
            <div class="space-y-3 rounded-lg border border-base-200 bg-base-200/40 p-4">
                <p class="text-sm text-base-content/80">{{ __('We cache your Gravatar on our server when you save or when you log in.') }}</p>
                <div class="flex items-center gap-3">
                    <span class="text-sm font-medium text-base-content/80">{{ __('Preview') }}</span>
                    <img
                        src="https://www.gravatar.com/avatar/{{ md5(strtolower(trim($userEmail))) }}?s=160&d=mp"
                        alt=""
                        class="h-14 w-14 rounded-full border border-base-300 object-cover"
                        loading="lazy"
                    />
                </div>
                <x-button type="button" class="btn-ghost btn-sm" wire:click="refreshRemoteAvatar">{{ __('Refresh from Gravatar now') }}</x-button>
            </div>
        @endif

        @if ($avatar_source === 'google')
            <div class="space-y-3 rounded-lg border border-base-200 bg-base-200/40 p-4">
                @if (auth()->user()->profile?->google_id)
                    <p class="text-sm text-base-content/80">{{ __('Uses the picture from your linked Google account. Refreshed on each Google sign-in and when you click below.') }}</p>
                    <div class="flex items-center gap-3">
                        <span class="text-sm font-medium text-base-content/80">{{ __('Current') }}</span>
                        <img src="{{ auth()->user()->avatarUrl() }}" alt="" class="h-14 w-14 rounded-full border border-base-300 object-cover" loading="lazy" />
                    </div>
                    <x-button type="button" class="btn-ghost btn-sm" wire:click="refreshRemoteAvatar">{{ __('Refresh from Google now') }}</x-button>
                @else
                    <p class="text-sm text-base-content/80">{{ __('Sign in once with Google to link your account, then you can use this avatar source.') }}</p>
                    <a href="{{ route('google.redirect', ['return_tab' => 'avatar']) }}" class="btn btn-primary btn-sm">{{ __('Link Google account') }}</a>
                @endif
            </div>
        @endif

        @if ($avatar_source === 'facebook')
            <div class="space-y-3 rounded-lg border border-base-200 bg-base-200/40 p-4">
                @if (auth()->user()->profile?->facebook_id)
                    <p class="text-sm text-base-content/80">{{ __('Uses the picture from your linked Facebook account. Refreshed on each Facebook sign-in and when you click below.') }}</p>
                    <div class="flex items-center gap-3">
                        <span class="text-sm font-medium text-base-content/80">{{ __('Current') }}</span>
                        <img src="{{ auth()->user()->avatarUrl() }}" alt="" class="h-14 w-14 rounded-full border border-base-300 object-cover" loading="lazy" />
                    </div>
                    <x-button type="button" class="btn-ghost btn-sm" wire:click="refreshRemoteAvatar">{{ __('Refresh from Facebook now') }}</x-button>
                @else
                    <p class="text-sm text-base-content/80">{{ __('Sign in once with Facebook to link your account, then you can use this avatar source.') }}</p>
                    <a href="{{ route('facebook.redirect', ['return_tab' => 'avatar']) }}" class="btn btn-primary btn-sm">{{ __('Link Facebook account') }}</a>
                @endif
            </div>
        @endif

        <div class="flex items-center justify-end gap-4">
            <x-action-message class="me-3" on="profile-avatar-updated">{{ __('Saved.') }}</x-action-message>
            <x-button class="btn-primary" type="submit">{{ __('Save') }}</x-button>
        </div>
    </form>
</section>
