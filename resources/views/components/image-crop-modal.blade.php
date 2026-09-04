@props([
    'title' => __('ui.common.crop_image'),
])

<dialog id="ui-image-crop-modal" class="modal backdrop-blur z-[100030]">
    <div class="modal-box max-w-lg ui-modal-surface">
        <h3 class="text-lg font-semibold" data-image-crop-modal-title>{{ $title }}</h3>
        <div class="ui-image-crop-stage mt-4 w-full" wire:ignore>
            <div class="ui-image-crop-canvas" data-image-crop-stage>
                <img data-image-crop-image alt="" class="block max-w-full" />
            </div>
            <input
                type="range"
                min="0"
                max="1"
                step="0.01"
                value="0"
                class="ui-image-crop-zoom mt-4 w-full"
                data-image-crop-zoom
                aria-label="Zoom"
            />
        </div>
        <div class="modal-action">
            <x-button type="button" class="btn-ghost" data-image-crop-cancel>{{ __('ui.common.cancel') }}</x-button>
            <x-button type="button" class="btn-primary" data-image-crop-apply>{{ __('ui.common.use_cropped_image') }}</x-button>
        </div>
    </div>
    <form method="dialog" class="modal-backdrop">
        <button type="submit" class="sr-only">{{ __('ui.common.cancel') }}</button>
    </form>
</dialog>
