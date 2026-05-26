@props([
    'aspect' => 'square',
    'wireProperty' => 'croppedImage',
    'clearMethod' => 'clearCroppedImage',
    'errorField' => 'croppedImage',
    'previewUrl' => null,
    'previewAlt' => '',
    'formSelector' => null,
    'fileInputId' => null,
    'modalTitle' => null,
    'uploadTitle' => __('Upload a photo'),
    'uploadHelp' => null,
    'chooseLabel' => __('Choose file'),
    'cropLabel' => __('Crop file'),
    'removeLabel' => __('Remove image'),
    'recropHint' => __('Click Crop file to adjust the crop before saving.'),
    'previewLabel' => __('Preview'),
    'outputSize' => '512,512',
    'fileName' => 'image.webp',
    'savedEvent' => null,
])

@php
    $isVideo = $aspect === 'video';
    $uploadHelpText = $uploadHelp ?? ($isVideo
        ? __('Drag and drop an image here, or use the button below. JPEG, PNG, or WebP. Crop to 16:9; stored as WebP after you save.')
        : __('Drag and drop an image here, or use the button below. JPEG, PNG, or WebP. After cropping, click Save below.'));
    $previewClasses = $isVideo
        ? 'aspect-video w-full max-w-md rounded-xl object-cover ring-2 ring-base-300/50'
        : 'h-48 w-48 rounded-full object-cover ring-2 ring-base-300/50 sm:h-56 sm:w-56';
    $placeholderClasses = $isVideo
        ? 'flex aspect-video w-full max-w-md items-center justify-center rounded-xl bg-base-300/40 ring-2 ring-base-300/50'
        : 'flex h-48 w-48 items-center justify-center rounded-xl bg-base-300/40 ring-2 ring-base-300/50 sm:h-56 sm:w-56';
    $resolvedFileInputId = $fileInputId ?? 'ui-image-crop-file-'.md5($wireProperty.$formSelector);
    $resolvedModalTitle = $modalTitle ?? ($isVideo ? __('Crop cover image') : __('Crop your avatar'));
@endphp

<div
    {{ $attributes->class(['ui-image-crop-dropzone grid gap-6 rounded-lg border border-base-200 bg-base-200/40 p-6 md:grid-cols-2 md:items-center md:gap-8']) }}
    data-image-crop-dropzone
    data-image-crop-aspect="{{ $aspect }}"
    data-image-crop-wire-property="{{ $wireProperty }}"
    data-image-crop-clear-method="{{ $clearMethod }}"
    data-image-crop-output="{{ $outputSize }}"
    data-image-crop-file-name="{{ $fileName }}"
    @if ($formSelector) data-image-crop-form="{{ $formSelector }}" @endif
    @if ($savedEvent) data-image-crop-saved-event="{{ $savedEvent }}" @endif
    data-image-crop-modal-title="{{ $resolvedModalTitle }}"
    data-label-choose="{{ $chooseLabel }}"
    data-label-crop="{{ $cropLabel }}"
    data-label-remove="{{ $removeLabel }}"
>
    <div class="flex flex-col gap-4">
        <input
            type="file"
            id="{{ $resolvedFileInputId }}"
            accept="image/jpeg,image/png,image/webp"
            class="sr-only"
            data-image-crop-file
        />
        <label
            for="{{ $resolvedFileInputId }}"
            class="ui-image-crop-dropzone-upload flex min-h-64 cursor-pointer flex-col items-center justify-center gap-4 rounded-xl border-2 border-dashed border-base-300/80 bg-base-100/30 p-8 text-center"
        >
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-10 text-base-content/50" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909M3.75 21h16.5A2.25 2.25 0 0 0 22.5 18.75V5.25A2.25 2.25 0 0 0 20.25 3H3.75A2.25 2.25 0 0 0 1.5 5.25v13.5A2.25 2.25 0 0 0 3.75 21Z" />
            </svg>
            <span class="text-base font-semibold text-base-content">{{ $uploadTitle }}</span>
            <p class="max-w-sm text-sm text-base-content/70">
                {{ $uploadHelpText }}
            </p>
            <span
                class="btn btn-primary btn-lg min-h-14 w-full max-w-sm px-8 text-base font-semibold"
                data-image-crop-file-trigger
            >
                <span data-image-crop-file-button-text>{{ $chooseLabel }}</span>
            </span>
            <p class="hidden max-w-sm text-sm text-base-content/70" data-image-crop-recrop-hint>
                {{ $recropHint }}
            </p>
        </label>
        <x-button
            type="button"
            class="btn-outline btn-md mx-auto hidden w-full max-w-sm"
            data-image-crop-remove
        >
            {{ $removeLabel }}
        </x-button>
        <x-field-error :messages="$errors->get($errorField)" class="mt-2" />
    </div>

    <div wire:ignore class="flex flex-col items-center justify-center gap-3">
        <span class="text-sm font-medium text-base-content/80">{{ $previewLabel }}</span>
        @if ($previewUrl)
            <img
                src="{{ $previewUrl }}"
                alt="{{ $previewAlt }}"
                class="{{ $previewClasses }}"
                loading="lazy"
                data-image-crop-preview
                data-default-src="{{ $previewUrl }}"
            />
        @else
            <div class="{{ $placeholderClasses }}" data-image-crop-preview-placeholder>
                <x-icon name="o-photo" class="size-12 text-base-content/40" />
            </div>
            <img
                src=""
                alt="{{ $previewAlt }}"
                class="{{ $previewClasses }} hidden"
                loading="lazy"
                data-image-crop-preview
                data-default-src=""
            />
        @endif
    </div>
</div>
