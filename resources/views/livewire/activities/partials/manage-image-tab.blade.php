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
        <div
            class="pointer-events-none grid gap-6 rounded-lg border border-base-200 bg-base-200/40 p-6 opacity-60 md:grid-cols-2 md:items-center md:gap-8"
            aria-disabled="true"
        >
            <div class="flex flex-col gap-4">
                <label
                    class="flex min-h-64 flex-col items-center justify-center gap-4 rounded-xl border-2 border-dashed border-base-300/80 bg-base-100/30 p-8 text-center"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-10 text-base-content/50" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909M3.75 21h16.5A2.25 2.25 0 0 0 22.5 18.75V5.25A2.25 2.25 0 0 0 20.25 3H3.75A2.25 2.25 0 0 0 1.5 5.25v13.5A2.25 2.25 0 0 0 3.75 21Z" />
                    </svg>
                    <span class="text-base font-semibold text-base-content">{{ __('ui.activities.image_upload') }}</span>
                    <p class="max-w-sm text-sm text-base-content/70">
                        {{ __('ui.activities.image_upload_coming_soon') }}
                    </p>
                </label>
            </div>
            <div class="flex flex-col items-center justify-center gap-3">
                <span class="text-sm font-medium text-base-content/80">{{ __('Preview') }}</span>
                <div class="flex h-48 w-48 items-center justify-center rounded-xl bg-base-300/40 ring-2 ring-base-300/50 sm:h-56 sm:w-56">
                    <x-icon name="o-photo" class="size-12 text-base-content/40" />
                </div>
            </div>
        </div>
    @endif
</div>
