<?php

use App\Actions\Media\DeleteUserGalleryImage;
use App\Actions\Media\StoreUserGalleryImage;
use App\Livewire\Profile\Concerns\ReportsProfileTabValidation;
use App\Support\Media\UserGalleryCatalog;
use Illuminate\Support\Facades\Auth;
use Livewire\Features\SupportFileUploads\WithFileUploads;
use Livewire\Volt\Component;

new class extends Component
{
    use ReportsProfileTabValidation;
    use WithFileUploads;

    /** @var mixed */
    public $croppedLogo = null;

    /** @var mixed */
    public $sourceImage = null;

    public ?int $confirmingDeleteMediaId = null;

    public function clearCroppedLogo(): void
    {
        $this->reset('croppedLogo');
    }

    public function clearSourceImage(): void
    {
        $this->reset('sourceImage');
    }

    /**
     * @return list<array{media_id: int, sources: \App\Support\Media\MediaPictureSources}>
     */
    public function getGalleryImagesProperty(): array
    {
        return app(UserGalleryCatalog::class)->forUser(Auth::user());
    }

    public function uploadImage(): void
    {
        $this->reportProfileTabValidation('images', function (): void {
            $this->validate([
                'croppedLogo' => ['required', 'image', 'max:5120', 'mimes:jpeg,jpg,png,webp'],
                'sourceImage' => ['nullable', 'image', 'max:12288', 'mimes:jpeg,jpg,png,webp'],
            ]);

            app(StoreUserGalleryImage::class)(
                Auth::user(),
                $this->croppedLogo,
                1280,
                720,
                $this->sourceImage,
            );

            $this->reset('croppedLogo', 'sourceImage');
            session()->flash('status', __('ui.profile.gallery_uploaded_success'));
        });
    }

    public function confirmDelete(int $mediaId): void
    {
        $this->confirmingDeleteMediaId = $mediaId;
    }

    public function cancelDelete(): void
    {
        $this->confirmingDeleteMediaId = null;
    }

    public function deleteImage(int $mediaId): void
    {
        $this->reportProfileTabValidation('images', function () use ($mediaId): void {
            app(DeleteUserGalleryImage::class)(Auth::user(), $mediaId);
            $this->confirmingDeleteMediaId = null;
            session()->flash('status', __('ui.profile.gallery_deleted_success'));
        });
    }
}; ?>

<section class="space-y-8" data-ui="profile-gallery-section">
    <div>
        <h2 class="text-lg font-semibold text-base-content">{{ __('ui.profile.gallery_title') }}</h2>
        <p class="mt-1 text-sm text-base-content/70">{{ __('ui.profile.gallery_hint') }}</p>
    </div>

    <form wire:submit="uploadImage" class="space-y-4" data-ui="profile-gallery-upload-form" data-profile-gallery-form>
        <x-image-crop-upload
            aspect="video"
            wire-property="croppedLogo"
            clear-method="clearCroppedLogo"
            error-field="croppedLogo"
            form-selector="data-profile-gallery-form"
            file-input-id="ui-profile-gallery-file"
            :upload-title="__('ui.profile.gallery_upload')"
            :upload-help="__('ui.common.cover_image_upload_help', ['max' => '5 MB'])"
            output-size="1280,720"
            file-name="gallery.webp"
            :modal-title="__('ui.common.crop_cover_image')"
        />

        <div class="flex justify-end">
            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                {{ __('ui.profile.gallery_upload_submit') }}
            </button>
        </div>
    </form>

    <div>
        <h3 class="mb-3 text-sm font-semibold text-base-content">{{ __('ui.profile.gallery_your_images') }}</h3>

        @if ($this->galleryImages === [])
            <p class="text-sm text-base-content/70">{{ __('ui.profile.gallery_empty') }}</p>
        @else
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($this->galleryImages as $image)
                    @php
                        $mediaId = (int) $image['media_id'];
                    @endphp
                    <div class="group relative overflow-hidden rounded-xl border border-base-300">
                        <x-media-picture
                            :sources="$image['sources']"
                            class="aspect-video w-full object-cover"
                        />
                        <div class="absolute inset-x-0 bottom-0 flex justify-end bg-gradient-to-t from-black/60 to-transparent p-2">
                            @if ($confirmingDeleteMediaId === $mediaId)
                                <div class="flex gap-2">
                                    <button
                                        type="button"
                                        class="btn btn-xs btn-error"
                                        wire:click="deleteImage({{ $mediaId }})"
                                    >
                                        {{ __('ui.profile.gallery_delete_confirm') }}
                                    </button>
                                    <button
                                        type="button"
                                        class="btn btn-xs"
                                        wire:click="cancelDelete"
                                    >
                                        {{ __('ui.common.cancel') }}
                                    </button>
                                </div>
                            @else
                                <button
                                    type="button"
                                    class="btn btn-xs btn-ghost text-white"
                                    wire:click="confirmDelete({{ $mediaId }})"
                                >
                                    {{ __('ui.profile.gallery_delete') }}
                                </button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
            <p class="mt-3 text-xs text-base-content/60">{{ __('ui.profile.gallery_delete_help') }}</p>
        @endif
    </div>
</section>
