<div class="relative space-y-6">
    <x-ui.livewire-loading-overlay
        target="logo_source"
        data-ui="manage-image-source-loading"
    />
    <fieldset class="fieldset py-0">
        <legend class="fieldset-legend mb-2">{{ __('ui.events.image_source') }}</legend>
        <div class="grid gap-3 sm:grid-cols-2">
            <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-base-300 p-3 has-[:checked]:border-primary has-[:checked]:bg-primary/5">
                <input
                    type="radio"
                    wire:model.live="logo_source"
                    name="logo_source"
                    value="default"
                    class="radio radio-primary mt-0.5"
                />
                <span>
                    <span class="block text-sm font-semibold text-base-content">{{ __('ui.events.image_from_defaults') }}</span>
                    <span class="mt-0.5 block text-xs text-base-content/70">{{ __('ui.events.image_from_defaults_help') }}</span>
                </span>
            </label>
            <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-base-300 p-3 has-[:checked]:border-primary has-[:checked]:bg-primary/5">
                <input
                    type="radio"
                    wire:model.live="logo_source"
                    name="logo_source"
                    value="upload"
                    class="radio radio-primary mt-0.5"
                />
                <span>
                    <span class="block text-sm font-semibold text-base-content">{{ __('ui.events.image_upload') }}</span>
                    <span class="mt-0.5 block text-xs text-base-content/70">{{ __('ui.events.image_upload_help') }}</span>
                </span>
            </label>
        </div>
        <x-field-error :messages="$errors->get('logo_source')" class="mt-2" />
    </fieldset>

    @if ($logo_source === 'default')
        <div class="rounded-lg border border-base-200 bg-base-200/40 p-6">
            @if ($this->availableDefaultImages === [])
                <p class="text-sm text-base-content/80">{{ __('ui.events.image_no_defaults') }}</p>
            @else
                <div
                    class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3"
                    role="radiogroup"
                    x-data="{ selectedMediaId: @entangle('listing_media_id').live }"
                >
                    @foreach ($this->availableDefaultImages as $image)
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
                                class="aspect-video w-full object-cover"
                            />
                        </button>
                    @endforeach
                </div>
                <x-field-error :messages="$errors->get('listing_media_id')" class="mt-4" />
            @endif
        </div>
    @endif

    @if ($logo_source === 'upload')
        <x-image-crop-upload
            aspect="video"
            wire-property="croppedLogo"
            clear-method="clearCroppedLogo"
            error-field="croppedLogo"
            form-selector="data-event-form"
            file-input-id="ui-event-logo-file"
            :preview-url="$logoPreviewUrl ?? null"
            :upload-title="__('ui.events.image_upload')"
            :upload-help="__('ui.events.image_upload_crop_help')"
            output-size="1280,720"
            file-name="logo.webp"
            :modal-title="__('ui.events.image_crop_title')"
        />
    @endif
</div>
