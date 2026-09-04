<?php

use App\Actions\Avatars\AttachUserAvatarFromPath;
use App\Actions\Avatars\RefreshCachedAvatar;
use App\Actions\Avatars\StoreUploadedAvatar;
use App\Actions\Media\StoreUserGalleryImage;
use App\Enums\AvatarSource;
use App\Models\User;
use App\Livewire\Profile\Concerns\ReportsProfileTabValidation;
use App\Support\Media\UserGalleryCatalog;
use App\Support\Ui\AvatarSlot;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Features\SupportFileUploads\WithFileUploads;
use Livewire\Volt\Component;

new class extends Component
{
    use ReportsProfileTabValidation;
    use WithFileUploads;

    public string $avatar_source = 'generated';

    public string $avatar_bg_color = '#1d4ed8';

    public string $avatar_text_color = '#ffffff';

    public string $avatar_initials = '';

    public ?int $gallery_media_id = null;

    /** @var mixed */
    public $croppedAvatar = null;

    /** @var mixed */
    public $sourceImage = null;

    public string $userEmail = '';

    public bool $avatarProcessingModalOpen = false;

    public function mount(): void
    {
        $user = Auth::user();
        $profile = $user->profile;
        $this->userEmail = (string) $user->email;
        $this->avatar_bg_color = $profile?->avatar_bg_color ?? '#1d4ed8';
        $this->avatar_text_color = $profile?->avatar_text_color ?? '#ffffff';
        $this->avatar_initials = $profile?->avatar_initials ?? '';
        $this->gallery_media_id = $profile?->gallery_media_id !== null
            ? (int) $profile->gallery_media_id
            : null;
        $src = $profile?->avatar_source;
        if ($src instanceof AvatarSource) {
            $this->avatar_source = $src->value;
        } elseif (is_string($src) && $src !== '') {
            $this->avatar_source = $src;
        } else {
            $this->avatar_source = AvatarSource::Generated->value;
        }
    }

    /**
     * @return list<array{media_id: int, sources: \App\Support\Media\MediaPictureSources}>
     */
    public function getAvailableGalleryImagesProperty(): array
    {
        return app(UserGalleryCatalog::class)->forUser(Auth::user());
    }

    public function updatedAvatarSource(string $value): void
    {
        $this->reset('croppedAvatar', 'sourceImage');

        if ($value !== AvatarSource::Gallery->value && $value !== AvatarSource::Uploaded->value) {
            $this->gallery_media_id = null;
        }
    }

    public function updatedGalleryMediaId(?int $value): void
    {
        $this->reset('croppedAvatar', 'sourceImage');
    }

    public function clearCroppedAvatar(): void
    {
        $this->reset('croppedAvatar');
    }

    public function clearSourceImage(): void
    {
        $this->reset('sourceImage');
    }

    public function remoteAvatarPreviewUrl(string $provider): string
    {
        $user = Auth::user();
        $profile = $user->profile;
        $url = match ($provider) {
            'facebook' => $profile?->facebook_avatar_url,
            'discord' => $profile?->discord_avatar_url,
            default => $profile?->google_avatar_url,
        };

        return (is_string($url) && $url !== '') ? $url : $user->avatarUrl(AvatarSlot::Preview);
    }

    public function dismissAvatarProcessingModal(): void
    {
        $this->avatarProcessingModalOpen = false;
    }

    #[On('profile-avatar-updated')]
    public function handleProfileAvatarUpdated(): void
    {
        $this->syncAvatarProcessingModal();
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
        $this->reportProfileTabValidation('avatar', function (): void {
            $user = Auth::user();
            $uploadedPath = 'avatars/'.$user->id.'.webp';
            $hasExistingUpload = $user->getFirstMedia('avatar') !== null
                || Storage::disk('public')->exists($uploadedPath);
            $rawPrev = $user->profile?->avatar_source;
            $previousSource = $rawPrev instanceof AvatarSource ? $rawPrev->value : (string) ($rawPrev ?? AvatarSource::Generated->value);

            $validated = $this->validate([
                'avatar_source' => ['required', 'string', Rule::in(array_map(static fn (AvatarSource $s) => $s->value, AvatarSource::cases()))],
                'avatar_bg_color' => ['required_if:avatar_source,generated', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
                'avatar_text_color' => ['required_if:avatar_source,generated', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
                'avatar_initials' => ['nullable', 'string', 'max:3', 'regex:/^[A-Za-z]{1,3}$/'],
                'gallery_media_id' => [
                    'nullable',
                    'integer',
                    Rule::requiredIf(fn (): bool => $this->avatar_source === AvatarSource::Gallery->value),
                    function (string $attribute, mixed $value, \Closure $fail): void {
                        if ($this->avatar_source !== AvatarSource::Gallery->value) {
                            return;
                        }
                        if ($value === null || $value === '') {
                            return;
                        }
                        if (! app(UserGalleryCatalog::class)->mediaBelongsToUser((int) $value, Auth::user())) {
                            $fail(__('ui.profile.image_invalid_gallery_media'));
                        }
                    },
                ],
                'croppedAvatar' => [
                    Rule::requiredIf(fn (): bool => $this->avatar_source === 'uploaded'
                        && ($previousSource !== AvatarSource::Uploaded->value || ! $hasExistingUpload)),
                    'nullable',
                    'image',
                    'max:5120',
                    'mimes:jpeg,jpg,png,webp',
                ],
                'sourceImage' => [
                    'nullable',
                    'image',
                    'max:12288',
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
                $profile->gallery_media_id = null;
                $profile->save();
                $this->dispatchProfileAvatarUpdated();

                return;
            }

            if ($source === AvatarSource::Gallery) {
                $previousGalleryMediaId = $profile->gallery_media_id !== null
                    ? (int) $profile->gallery_media_id
                    : null;
                $selectedGalleryMediaId = $this->gallery_media_id !== null
                    ? (int) $this->gallery_media_id
                    : null;

                $profile->avatar_source = AvatarSource::Gallery;
                $profile->gallery_media_id = $selectedGalleryMediaId;
                $profile->avatar_path = null;
                $profile->avatar_cache_signature = null;

                if ($this->croppedAvatar !== null) {
                    app(StoreUploadedAvatar::class)($user, $this->croppedAvatar);
                    $user->clearMediaCollection('source');
                } elseif ($selectedGalleryMediaId !== $previousGalleryMediaId) {
                    $this->deleteStoredAvatarIfPresent($user->id);
                }

                $profile->save();
                $this->reset('croppedAvatar', 'sourceImage');
                $this->dispatchProfileAvatarUpdated();

                return;
            }

            if ($source === AvatarSource::Uploaded) {
                if ($this->croppedAvatar !== null) {
                    $this->deleteStoredAvatarIfPresent($user->id);
                    $media = app(StoreUserGalleryImage::class)(
                        $user,
                        $this->croppedAvatar,
                        512,
                        512,
                        $this->sourceImage,
                    );
                    $profile->avatar_source = AvatarSource::Gallery;
                    $profile->gallery_media_id = (int) $media->id;
                    $profile->avatar_path = null;
                    $profile->avatar_cache_signature = null;
                    $this->avatar_source = AvatarSource::Gallery->value;
                    $this->gallery_media_id = (int) $media->id;
                } else {
                    $profile->avatar_source = AvatarSource::Uploaded;
                    $profile->gallery_media_id = null;
                    $profile->avatar_bg_color = $validated['avatar_bg_color'] ?? $profile->avatar_bg_color;
                    $profile->avatar_text_color = $validated['avatar_text_color'] ?? $profile->avatar_text_color;
                }
                $profile->save();
                $this->reset('croppedAvatar', 'sourceImage');
                $this->dispatchProfileAvatarUpdated();

                return;
            }

            $profile->gallery_media_id = null;

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

            if ($source === AvatarSource::Discord) {
                if ($profile->discord_id === null || $profile->discord_id === '') {
                    $this->addError('avatar_source', __('Link your Discord account first using the button below.'));

                    return;
                }
                $profile->avatar_source = AvatarSource::Discord;
                $profile->save();
                try {
                    app(RefreshCachedAvatar::class)($user->fresh(), AvatarSource::Discord);
                } catch (\Throwable $e) {
                    $this->addError('avatar', $e->getMessage());

                    return;
                }
                $this->dispatchProfileAvatarUpdated();
            }
        });
    }

    private function dispatchProfileAvatarUpdated(): void
    {
        $user = Auth::user()->fresh(['profile.galleryMedia', 'media']);
        $url = $user->avatarUrl(AvatarSlot::Preview);
        $version = $user->profile?->updated_at?->getTimestamp() ?? time();

        $this->dispatch('profile-avatar-updated', avatarUrl: $this->cacheBustedAvatarUrl($url, $version));
        $this->syncAvatarProcessingModal();
    }

    private function syncAvatarProcessingModal(): void
    {
        $user = Auth::user()?->fresh(['media']);

        $this->avatarProcessingModalOpen = $user?->avatarConversionsPending() ?? false;
    }

    private function cacheBustedAvatarUrl(string $url, int $version): string
    {
        $separator = str_contains($url, '?') ? '&' : '?';

        return $url.$separator.'v='.$version;
    }

    private function deleteStoredAvatarIfPresent(int $userId): void
    {
        $user = Auth::user();

        if ($user !== null && (int) $user->id === $userId) {
            $user->clearMediaCollection('avatar');
            $user->clearMediaCollection('source');
        } elseif (($resolved = User::query()->find($userId)) !== null) {
            $resolved->clearMediaCollection('avatar');
            $resolved->clearMediaCollection('source');
        }

        AttachUserAvatarFromPath::deleteLegacyAvatarFileForUserId($userId);
    }
}; ?>

<section id="ui-profile-avatar-section" class="ui-profile-section ui-profile-avatar" data-ui="profile-avatar-section">
    <x-ui.form-errors :title="__('ui.status.oops')" :description="__('ui.status.fix_errors')" icon="o-face-frown" class="!mx-0 mb-4" />
    <form id="ui-profile-avatar-form" wire:submit="updateAvatar" novalidate class="ui-form ui-form-profile-avatar space-y-6" data-ui="profile-avatar-form">
        <x-field-error :messages="$errors->get('avatar')" class="mt-2" />
        <x-field-error :messages="$errors->get('avatar_source')" class="mt-2" />

        <fieldset class="fieldset py-0">
            <legend class="fieldset-legend mb-2">{{ __('ui.profile.avatar_source') }}</legend>
            <div class="grid gap-3 sm:grid-cols-2">
                <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-base-300 p-3 has-[:checked]:border-primary has-[:checked]:bg-primary/5">
                    <input type="radio" wire:model.live="avatar_source" name="avatar_source" value="generated" class="radio radio-primary mt-0.5" />
                    <span>
                        <span class="block text-sm font-semibold text-base-content">{{ __('ui.profile.avatar_generated') }}</span>
                        <span class="mt-0.5 block text-xs text-base-content/70">{{ __('ui.common.initials_source_hint') }}</span>
                    </span>
                </label>
                <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-base-300 p-3 has-[:checked]:border-primary has-[:checked]:bg-primary/5">
                    <input type="radio" wire:model.live="avatar_source" name="avatar_source" value="uploaded" class="radio radio-primary mt-0.5" />
                    <span>
                        <span class="block text-sm font-semibold text-base-content">{{ __('ui.profile.avatar_uploaded') }}</span>
                        <span class="mt-0.5 block text-xs text-base-content/70">{{ __('ui.common.uploaded_image_hint') }}</span>
                    </span>
                </label>
                <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-base-300 p-3 has-[:checked]:border-primary has-[:checked]:bg-primary/5">
                    <input type="radio" wire:model.live="avatar_source" name="avatar_source" value="gallery" class="radio radio-primary mt-0.5" />
                    <span>
                        <span class="block text-sm font-semibold text-base-content">{{ __('ui.profile.avatar_gallery') }}</span>
                        <span class="mt-0.5 block text-xs text-base-content/70">{{ __('ui.profile.avatar_from_gallery_hint') }}</span>
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
                <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-base-300 p-3 has-[:checked]:border-primary has-[:checked]:bg-primary/5">
                    <input type="radio" wire:model.live="avatar_source" name="avatar_source" value="facebook" class="radio radio-primary mt-0.5" />
                    <span>
                        <span class="block text-sm font-semibold text-base-content">{{ __('ui.profile.avatar_facebook') }}</span>
                        <span class="mt-0.5 block text-xs text-base-content/70">{{ __('ui.profile.avatar_facebook_hint') }}</span>
                    </span>
                </label>
                <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-base-300 p-3 has-[:checked]:border-primary has-[:checked]:bg-primary/5">
                    <input type="radio" wire:model.live="avatar_source" name="avatar_source" value="discord" class="radio radio-primary mt-0.5" />
                    <span>
                        <span class="block text-sm font-semibold text-base-content">{{ __('ui.profile.avatar_discord') }}</span>
                        <span class="mt-0.5 block text-xs text-base-content/70">{{ __('ui.profile.avatar_discord_hint') }}</span>
                    </span>
                </label>
            </div>
        </fieldset>

        @if ($avatar_source === 'generated')
            <div class="ui-profile-avatar-source-panel grid gap-6 rounded-lg border border-base-200 bg-base-200/40 p-6 md:grid-cols-2 md:items-center md:gap-8">
                <div class="flex flex-col gap-4">
                    <x-input
                        wire:model.live="avatar_initials"
                        label="{{ __('ui.profile.avatar_initials') }}"
                        placeholder="{{ __('ui.profile.avatar_initials') }}"
                        type="text"
                        name="avatar_initials"
                        error-field="avatar_initials"
                        maxlength="3"
                        inline
                    />
                    <x-colorpicker wire:model.live="avatar_bg_color" label="{{ __('ui.common.bg_color') }}" name="avatar_bg_color" error-field="avatar_bg_color" required inline />
                    <x-colorpicker wire:model.live="avatar_text_color" label="{{ __('ui.common.text_color') }}" name="avatar_text_color" error-field="avatar_text_color" required inline />
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
                :preview-url="auth()->user()->avatarUrl(\App\Support\Ui\AvatarSlot::Preview)"
                :source-url="auth()->user()->cropSourceImageUrl()"
                output-size="512,512"
                file-name="avatar.webp"
                :modal-title="__('ui.profile.crop_avatar')"
            />
        @endif

        @if ($avatar_source === 'gallery')
            @php
                $galleryCatalog = app(\App\Support\Media\UserGalleryCatalog::class);
                $selectedGalleryMedia = $gallery_media_id !== null
                    ? $galleryCatalog->findForUser((int) $gallery_media_id, auth()->user())
                    : null;
                $galleryCropSourceUrl = $selectedGalleryMedia !== null
                    ? $galleryCatalog->sourceUrl($selectedGalleryMedia)
                    : null;
                $galleryCropPreviewUrl = null;
                if ($selectedGalleryMedia !== null
                    && auth()->user()->profile?->avatar_source === \App\Enums\AvatarSource::Gallery
                    && (int) auth()->user()->profile?->gallery_media_id === (int) $gallery_media_id
                    && auth()->user()->getFirstMedia('avatar') !== null
                ) {
                    $galleryCropPreviewUrl = auth()->user()->avatarUrl(\App\Support\Ui\AvatarSlot::Preview);
                } elseif ($selectedGalleryMedia !== null) {
                    $galleryCropPreviewUrl = $galleryCatalog->previewUrl($selectedGalleryMedia);
                }
            @endphp
            <div class="space-y-6">
                <div class="rounded-lg border border-base-200 bg-base-200/40 p-6">
                    @if ($this->availableGalleryImages === [])
                        <p class="text-sm text-base-content/80">{{ __('ui.profile.avatar_gallery_empty') }}</p>
                    @else
                        <div
                            class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3"
                            role="radiogroup"
                            x-data="{ selectedMediaId: @entangle('gallery_media_id').live }"
                        >
                            @foreach ($this->availableGalleryImages as $image)
                                @php
                                    $mediaId = (int) $image['media_id'];
                                @endphp
                                <button
                                    type="button"
                                    role="radio"
                                    :aria-checked="Number(selectedMediaId) === {{ $mediaId }}"
                                    @click="selectedMediaId = {{ $mediaId }}"
                                    :class="Number(selectedMediaId) === {{ $mediaId }}
                                        ? 'border-primary ring-2 ring-primary'
                                        : 'border-base-300 hover:border-primary/50'"
                                    class="group relative cursor-pointer overflow-hidden rounded-xl border-2 text-left"
                                >
                                    <x-media-picture
                                        :sources="$image['sources']"
                                        class="aspect-square w-full object-cover"
                                    />
                                </button>
                            @endforeach
                        </div>
                        <x-field-error :messages="$errors->get('gallery_media_id')" class="mt-4" />
                    @endif
                </div>

                @if ($gallery_media_id !== null && filled($galleryCropSourceUrl))
                    <x-image-crop-upload
                        aspect="square"
                        wire-property="croppedAvatar"
                        clear-method="clearCroppedAvatar"
                        error-field="croppedAvatar"
                        form-selector="#ui-profile-avatar-form"
                        file-input-id="ui-profile-gallery-crop-file"
                        :preview-url="$galleryCropPreviewUrl"
                        :source-url="$galleryCropSourceUrl"
                        :upload-title="__('ui.profile.crop_avatar')"
                        :upload-help="__('ui.common.recrop_saved_hint')"
                        :choose-label="__('ui.common.crop_again')"
                        output-size="512,512"
                        file-name="avatar.webp"
                        :modal-title="__('ui.profile.crop_avatar')"
                    />
                @endif
            </div>
        @endif

        @if ($avatar_source === 'gravatar')
            <div class="ui-profile-avatar-source-panel grid gap-6 rounded-lg border border-base-200 bg-base-200/40 p-6 md:grid-cols-2 md:items-center md:gap-8">
                <div class="flex flex-col gap-4">
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
                        <x-button type="button" class="btn-outline btn-md w-full max-w-sm" wire:click="refreshRemoteAvatar">{{ __('ui.profile.avatar_refresh_google') }}</x-button>
                    @elseif (config('services.google.client_id'))
                        {{-- OAuth must use full document navigation; wire:navigate would fetch redirect → CORS on accounts.google.com --}}
                        <x-button :link="route('google.redirect', ['return_tab' => 'avatar'])" :no-wire-navigate="true" class="btn-primary btn-lg min-h-14 w-full max-w-sm px-8 text-base font-semibold">{{ __('ui.profile.avatar_link_google') }}</x-button>
                    @endif
                </div>
                <div class="flex flex-col items-center justify-center gap-3">
                    <span class="text-sm font-medium text-base-content/80">{{ auth()->user()->profile?->google_id ? __('ui.profile.avatar_current') : __('ui.common.preview') }}</span>
                    <img
                        src="{{ $this->remoteAvatarPreviewUrl('google') }}"
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
                        <x-button type="button" class="btn-outline btn-md w-full max-w-sm" wire:click="refreshRemoteAvatar">{{ __('ui.profile.avatar_refresh_facebook') }}</x-button>
                    @elseif (config('services.facebook.client_id'))
                        {{-- OAuth must use full document navigation; wire:navigate would fetch redirect → CORS on facebook.com --}}
                        <x-button :link="route('facebook.redirect', ['return_tab' => 'avatar'])" :no-wire-navigate="true" class="btn-primary btn-lg min-h-14 w-full max-w-sm px-8 text-base font-semibold">{{ __('ui.profile.avatar_link_facebook') }}</x-button>
                    @endif
                </div>
                <div class="flex flex-col items-center justify-center gap-3">
                    <span class="text-sm font-medium text-base-content/80">{{ auth()->user()->profile?->facebook_id ? __('ui.profile.avatar_current') : __('ui.common.preview') }}</span>
                    <img
                        src="{{ $this->remoteAvatarPreviewUrl('facebook') }}"
                        alt=""
                        class="h-48 w-48 rounded-full object-cover ring-2 ring-base-300/50 sm:h-56 sm:w-56"
                        loading="lazy"
                    />
                </div>
            </div>
        @endif

        @if ($avatar_source === 'discord')
            <div class="ui-profile-avatar-source-panel grid gap-6 rounded-lg border border-base-200 bg-base-200/40 p-6 md:grid-cols-2 md:items-center md:gap-8">
                <div class="flex flex-col gap-4">
                    @if (auth()->user()->profile?->discord_id)
                        <x-button type="button" class="btn-outline btn-md w-full max-w-sm" wire:click="refreshRemoteAvatar">{{ __('ui.profile.avatar_refresh_discord') }}</x-button>
                    @elseif (config('services.discord.client_id'))
                        {{-- OAuth must use full document navigation; wire:navigate would fetch redirect → CORS on discord.com --}}
                        <x-button :link="route('discord.redirect', ['return_tab' => 'avatar'])" :no-wire-navigate="true" class="btn-primary btn-lg min-h-14 w-full max-w-sm px-8 text-base font-semibold">{{ __('ui.profile.avatar_link_discord') }}</x-button>
                    @endif
                </div>
                <div class="flex flex-col items-center justify-center gap-3">
                    <span class="text-sm font-medium text-base-content/80">{{ auth()->user()->profile?->discord_id ? __('ui.profile.avatar_current') : __('ui.common.preview') }}</span>
                    <img
                        src="{{ $this->remoteAvatarPreviewUrl('discord') }}"
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

    <x-modal
        wire:model="avatarProcessingModalOpen"
        :title="__('ui.profile.avatar_processing_title')"
        class="backdrop-blur ui-modal ui-modal-avatar-processing"
        box-class="ui-modal-surface max-w-md"
        separator
        data-ui="profile-avatar-processing-modal"
    >
        <div class="flex flex-col items-center gap-4 py-2 text-center">
            <p class="text-sm text-base-content/80">{{ __('ui.profile.avatar_processing_body') }}</p>
        </div>
        <div class="modal-action">
            <x-button type="button" class="btn-primary" wire:click="dismissAvatarProcessingModal" data-ui="profile-avatar-processing-dismiss">
                {{ __('ui.profile.avatar_processing_dismiss') }}
            </x-button>
        </div>
    </x-modal>
</section>
