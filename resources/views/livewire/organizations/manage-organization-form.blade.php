@push('head')
    <script src="https://cdn.jsdelivr.net/npm/tinymce@7/tinymce.min.js" referrerpolicy="origin"></script>
@endpush

@php
    $title = $editingOrganizationId
        ? (__('ui.organizations.edit').': '.$this->name)
        : __('ui.organizations.create_title');
@endphp
<div>
    <x-page-header :title="$title" :user="$creator" :back-url="$backUrl" class="mb-4" />

    <x-ui.form-errors :title="__('ui.status.oops')" :description="__('ui.status.fix_errors')" icon="o-face-frown" class="mb-10" />

    <div class="ui-content-card relative min-w-0 rounded-2xl mb-4 md:mb-6">
        <x-form wire:submit.prevent="save" novalidate class="" data-org-form>
            <div id="ui-organization-form-fields" class="ui-form ui-form-organization min-w-0 space-y-4 px-4 py-4 sm:px-6 sm:py-6" data-ui="organization-form-fields">
                <x-input
                    wire:model="name"
                    label="{{ __('ui.common.name') }}"
                    placeholder="{{ __('ui.common.name') }}"
                    type="text"
                    error-field="name"
                    required
                    inline
                />

                <x-input
                    wire:model.live="acronym"
                    label="{{ __('ui.organizations.acronym') }}"
                    type="text"
                    name="acronym"
                    error-field="acronym"
                    maxlength="5"
                />
                <p class="-mt-2 text-xs text-base-content/70">{{ __('ui.organizations.acronym_hint') }}</p>

                <div>
                    <x-editor
                        id="org-description"
                        wire:model="description"
                        :label="__('ui.organizations.description')"
                        :gpl-license="true"
                        :config="['height' => 260]"
                    />
                    <x-field-error :messages="$errors->get('description')" class="mt-2" />
                </div>

                <fieldset class="fieldset py-0">
                    <legend class="fieldset-legend mb-2">{{ __('ui.organizations.logo_source') }}</legend>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-base-300 p-3 has-[:checked]:border-primary has-[:checked]:bg-primary/5">
                            <input type="radio" wire:model.live="logo_source" name="logo_source" value="generated" class="radio radio-primary mt-0.5" />
                            <span>
                                <span class="block text-sm font-semibold text-base-content">{{ __('ui.organizations.logo_generated') }}</span>
                                <span class="mt-0.5 block text-xs text-base-content/70">{{ __('ui.common.initials_source_hint') }}</span>
                            </span>
                        </label>
                        <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-base-300 p-3 has-[:checked]:border-primary has-[:checked]:bg-primary/5">
                            <input type="radio" wire:model.live="logo_source" name="logo_source" value="upload" class="radio radio-primary mt-0.5" />
                            <span>
                                <span class="block text-sm font-semibold text-base-content">{{ __('ui.organizations.logo_uploaded') }}</span>
                                <span class="mt-0.5 block text-xs text-base-content/70">{{ __('ui.common.uploaded_image_hint') }}</span>
                            </span>
                        </label>
                    </div>
                    <x-field-error :messages="$errors->get('logo_source')" class="mt-2" />
                </fieldset>

                @if ($logo_source === 'generated')
                    <div class="grid gap-4 rounded-lg border border-base-200 bg-base-200/40 p-4 md:grid-cols-2 md:items-center">
                        <div class="flex flex-col gap-3">
                            <x-colorpicker wire:model.live="logo_bg_color" label="{{ __('ui.common.bg_color') }}" placeholder="{{ __('ui.common.bg_color') }}" name="logo_bg_color" error-field="logo_bg_color" inline required />
                            <x-colorpicker wire:model.live="logo_text_color" label="{{ __('ui.common.text_color') }}" placeholder="{{ __('ui.common.text_color') }}" name="logo_text_color" error-field="logo_text_color" inline required />
                        </div>
                        <div class="flex flex-col items-center justify-center gap-2">
                            <span class="text-sm font-medium text-base-content/80">{{ __('ui.common.preview') }}</span>
                            <img
                                src="{{ $this->generatedLogoPreviewUrl }}"
                                alt=""
                                class="h-20 w-20 rounded-full object-cover ring-2 ring-base-300/50"
                                loading="lazy"
                            />
                        </div>
                    </div>
                @endif

                @if ($logo_source === 'upload')
                    <x-image-crop-upload
                        compact
                        aspect="square"
                        wire-property="croppedLogo"
                        clear-method="clearCroppedLogo"
                        error-field="croppedLogo"
                        form-selector="[data-org-form]"
                        file-input-id="ui-org-logo-file"
                        :preview-url="$this->logoPreviewUrl"
                        :source-url="$this->cropSourceImageUrl"
                        output-size="512,512"
                        file-name="logo.webp"
                        :modal-title="__('ui.organizations.crop_logo')"
                    />
                @endif
            </div>

            <x-slot:actions class="px-4 pb-4 sm:px-6 sm:pb-6">
                <x-button id="ui-organization-cancel" :link="$cancelUrl" class="btn-outline ui-action ui-action-cancel" data-ui="organization-cancel">
                    {{ __('ui.common.cancel') }}
                </x-button>
                <x-button id="ui-organization-submit" class="btn-primary ui-action ui-action-submit" type="submit" data-ui="organization-submit" wire:loading.attr="disabled" wire:target="save" spinner="save">
                    <span wire:loading.remove wire:target="save">{{ $submitLabel }}</span>
                    <span wire:loading wire:target="save">{{ __('ui.common.saving') }}</span>
                </x-button>
            </x-slot:actions>
        </x-form>
    </div>

    <x-image-crop-modal :title="__('ui.organizations.crop_logo')" />
</div>
