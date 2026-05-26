@props([
    'title' => __('Crop image'),
])

<dialog id="ui-image-crop-modal" class="modal backdrop-blur">
    <div class="modal-box max-w-lg ui-modal-surface">
        <h3 class="text-lg font-semibold" data-image-crop-modal-title>{{ $title }}</h3>
        <div class="ui-image-crop-crop mt-4 w-full" wire:ignore>
            <div class="w-full" data-image-crop-croppie></div>
        </div>
        <div class="modal-action">
            <x-button type="button" class="btn-ghost" data-image-crop-cancel>{{ __('Cancel') }}</x-button>
            <x-button type="button" class="btn-primary" data-image-crop-apply>{{ __('Use cropped image') }}</x-button>
        </div>
    </div>
    <form method="dialog" class="modal-backdrop">
        <button type="submit" class="sr-only">{{ __('Cancel') }}</button>
    </form>
</dialog>
