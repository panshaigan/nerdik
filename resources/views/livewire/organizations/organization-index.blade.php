<div class="py-12 p-1">
    <div class="mx-auto max-w-3xl sm:px-6 lg:px-8">
        @if (session('status'))
            <div role="status" class="alert alert-success mb-4 text-sm">
                {{ session('status') }}
            </div>
        @endif

        <div class="mb-6 flex justify-end">
            <x-button
                type="button"
                class="btn-primary btn-circle shadow-sm touch-manipulation"
                wire:click="openCreateModal"
                :title="__('ui.organizations.add')"
                :aria-label="__('ui.organizations.add')"
            >
                <svg class="h-6 w-6 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
            </x-button>
        </div>

        <ul class="space-y-3" role="list">
            @forelse ($organizations as $organization)
                <li
                    wire:key="org-{{ $organization->id }}"
                    class="flex items-start gap-3 rounded-lg border border-base-300 bg-base-100 p-4 shadow-sm"
                >
                    <div class="avatar shrink-0">
                        <div class="h-10 w-10 overflow-hidden rounded-full border border-base-300 bg-base-300">
                            <img
                                src="{{ $organization->logoUrl() }}"
                                alt="{{ $organization->name }}"
                                class="h-full w-full object-cover"
                                loading="lazy"
                            />
                        </div>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="font-medium text-base-content">{{ $organization->name }}</p>
                        @if (filled(rich_text_excerpt($organization->description)))
                            <div class="rich-text-content mt-2 text-base-content/80">
                                {!! rich_text($organization->description) !!}
                            </div>
                        @endif
                    </div>
                    @canModifyEntity($organization)
                    <div class="flex shrink-0 items-center gap-1">
                        <x-button
                            type="button"
                            class="btn-ghost btn-square btn-sm text-base-content/80 hover:text-primary"
                            wire:click="openEditModal({{ $organization->id }})"
                            :title="__('ui.common.edit')"
                            :aria-label="__('ui.common.edit').': '.$organization->name"
                        >
                            <x-ui.icons.pencil class="h-5 w-5 shrink-0" />
                        </x-button>
                        <x-button
                            type="button"
                            class="btn-ghost btn-square btn-sm text-base-content/80 hover:text-error"
                            wire:click="deleteOrganization({{ $organization->id }})"
                            wire:confirm="{{ __('ui.organizations.delete_confirm') }}"
                            :title="__('ui.common.delete')"
                            :aria-label="__('ui.common.delete').': '.$organization->name"
                        >
                            <x-ui.icons.trash class="h-5 w-5 shrink-0" />
                        </x-button>
                    </div>
                    @endcanModifyEntity
                </li>
            @empty
                <li class="rounded-lg border border-dashed border-base-300 bg-base-100/50 px-4 py-8 text-center text-sm text-base-content/70">
                    {{ __('ui.organizations.empty') }}
                </li>
            @endforelse
        </ul>

        <x-modal
            wire:model="modalOpen"
            without-trap-focus
            :title="$modalMode === 'create' ? __('ui.organizations.add') : __('ui.organizations.edit')"
            box-class="max-w-2xl"
            data-org-modal
        >
            @if ($modalOpen)
                <form
                    wire:submit.prevent="save"
                    wire:key="org-modal-form-{{ $modalRenderKey }}-{{ $modalMode }}-{{ $editingOrganizationId ?? 'new' }}"
                    class="space-y-4"
                    data-org-modal-form
                >
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

                    <div wire:key="org-modal-editor-{{ $modalRenderKey }}">
                        <x-editor
                            id="org-description-{{ $modalRenderKey }}"
                            wire:model="description"
                            :label="__('ui.organizations.description')"
                            :gpl-license="true"
                            :config="['height' => 260, 'z_index' => 100020]"
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
                                    <span class="mt-0.5 block text-xs text-base-content/70">{{ __('ui.organizations.logo_generated_hint') }}</span>
                                </span>
                            </label>
                            <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-base-300 p-3 has-[:checked]:border-primary has-[:checked]:bg-primary/5">
                                <input type="radio" wire:model.live="logo_source" name="logo_source" value="upload" class="radio radio-primary mt-0.5" />
                                <span>
                                    <span class="block text-sm font-semibold text-base-content">{{ __('ui.organizations.logo_uploaded') }}</span>
                                    <span class="mt-0.5 block text-xs text-base-content/70">{{ __('ui.organizations.logo_uploaded_hint') }}</span>
                                </span>
                            </label>
                        </div>
                        <x-field-error :messages="$errors->get('logo_source')" class="mt-2" />
                    </fieldset>

                    @if ($logo_source === 'generated')
                        <div class="grid gap-4 rounded-lg border border-base-200 bg-base-200/40 p-4 md:grid-cols-2 md:items-center">
                            <div class="flex flex-col gap-3">
                                <p class="text-sm text-base-content/80">{{ __('ui.organizations.logo_colors_hint') }}</p>
                                <x-colorpicker wire:model.live="logo_bg_color" label="{{ __('ui.organizations.logo_bg_color') }}" name="logo_bg_color" error-field="logo_bg_color" required />
                                <x-colorpicker wire:model.live="logo_text_color" label="{{ __('ui.organizations.logo_text_color') }}" name="logo_text_color" error-field="logo_text_color" required />
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
                            form-selector="[data-org-modal-form]"
                            file-input-id="ui-org-logo-file"
                            :preview-url="$this->logoPreviewUrl"
                            output-size="512,512"
                            file-name="logo.webp"
                            :modal-title="__('ui.organizations.crop_logo')"
                        />
                    @endif

                    <div class="modal-action">
                        <x-button type="button" class="btn-ghost" wire:click="closeModal">
                            {{ __('ui.common.cancel') }}
                        </x-button>
                        <x-button type="submit" class="btn-primary">
                            {{ __('ui.common.save') }}
                        </x-button>
                    </div>
                </form>
            @endif
        </x-modal>

        <x-image-crop-modal :title="__('ui.organizations.crop_logo')" />
    </div>
</div>
