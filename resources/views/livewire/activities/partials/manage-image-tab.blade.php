<div class="space-y-6">
    <fieldset class="fieldset py-0">
        <legend class="fieldset-legend mb-2">{{ __('ui.activities.image_source') }}</legend>
        <div class="grid gap-3 sm:grid-cols-2">
            <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-base-300 p-3 has-[:checked]:border-primary has-[:checked]:bg-primary/5">
                <input
                    type="radio"
                    wire:model.live="logo_source"
                    name="logo_source"
                    value="tag"
                    class="radio radio-primary mt-0.5"
                />
                <span>
                    <span class="block text-sm font-semibold text-base-content">{{ __('ui.activities.image_from_tags') }}</span>
                    <span class="mt-0.5 block text-xs text-base-content/70">{{ __('ui.activities.image_from_tags_help') }}</span>
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
                    <span class="block text-sm font-semibold text-base-content">{{ __('ui.activities.image_upload') }}</span>
                    <span class="mt-0.5 block text-xs text-base-content/70">{{ __('ui.activities.image_upload_help') }}</span>
                </span>
            </label>
        </div>
        <x-field-error :messages="$errors->get('logo_source')" class="mt-2" />
    </fieldset>

    @if ($logo_source === 'tag')
        <div class="rounded-lg border border-base-200 bg-base-200/40 p-6">
            @if ($tag_ids === [])
                <p class="text-sm text-base-content/80">{{ __('ui.activities.image_pick_tags_first') }}</p>
            @elseif ($this->availableTagImages === [])
                <p class="text-sm text-base-content/80">{{ __('ui.activities.image_no_tag_images') }}</p>
            @else
                <div
                    class="space-y-8"
                    x-data="{ selectedMediaId: @entangle('selected_tag_media_id').live }"
                >
                    @foreach ($this->availableTagImages as $tagGroup)
                        <div>
                            <h3 class="mb-3 text-sm font-semibold text-base-content">{{ $tagGroup['label'] }}</h3>
                            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3" role="radiogroup">
                                @foreach ($tagGroup['images'] as $image)
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
                        </div>
                    @endforeach
                </div>
                <x-field-error :messages="$errors->get('selected_tag_media_id')" class="mt-4" />
            @endif
        </div>
    @endif

    @if ($logo_source === 'upload')
        <x-image-crop-upload
            aspect="video"
            wire-property="croppedLogo"
            clear-method="clearCroppedLogo"
            error-field="croppedLogo"
            form-selector="data-activity-form"
            file-input-id="ui-activity-logo-file"
            :preview-url="$logoPreviewUrl ?? null"
            :upload-title="__('ui.activities.image_upload')"
            :upload-help="__('ui.activities.image_upload_crop_help')"
            output-size="1280,720"
            file-name="logo.webp"
            :modal-title="__('ui.activities.image_crop_title')"
        />
    @endif
</div>
