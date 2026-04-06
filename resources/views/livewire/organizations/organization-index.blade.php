<div class="py-12">
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
                :title="__('Add organization')"
                :aria-label="__('Add organization')"
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
                    <div class="min-w-0 flex-1">
                        <p class="font-medium text-base-content">{{ $organization->name }}</p>
                        @if (filled(rich_text_excerpt($organization->description)))
                            <div class="rich-text-content mt-2 text-sm text-base-content/80">
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
                            :title="__('Edit')"
                            :aria-label="__('Edit').': '.$organization->name"
                        >
                            <x-ui.icons.pencil class="h-5 w-5 shrink-0" />
                        </x-button>
                        <x-button
                            type="button"
                            class="btn-ghost btn-square btn-sm text-base-content/80 hover:text-error"
                            wire:click="deleteOrganization({{ $organization->id }})"
                            wire:confirm="{{ __('Are you sure you want to delete this organization?') }}"
                            :title="__('Delete')"
                            :aria-label="__('Delete').': '.$organization->name"
                        >
                            <x-ui.icons.trash class="h-5 w-5 shrink-0" />
                        </x-button>
                    </div>
                    @endcanModifyEntity
                </li>
            @empty
                <li class="rounded-lg border border-dashed border-base-300 bg-base-100/50 px-4 py-8 text-center text-sm text-base-content/70">
                    {{ __('You have no organizations yet.') }}
                </li>
            @endforelse
        </ul>

        <x-modal
            wire:model="modalOpen"
            :title="$modalMode === 'create' ? __('Add organization') : __('Edit organization')"
            box-class="max-w-2xl"
        >
            @if ($modalOpen)
                <form
                    wire:submit.prevent="save"
                    wire:key="org-modal-form-{{ $modalMode }}-{{ $editingOrganizationId ?? 'new' }}"
                    class="space-y-4"
                    data-org-modal-form
                >
                    <x-input
                        wire:model="name"
                        label="{{ __('Name') }}"
                        type="text"
                        error-field="name"
                        required
                    />

                    <div>
                        <x-editor
                            wire:model.live="description"
                            :label="__('Description (optional)')"
                            :gpl-license="true"
                            :config="['height' => 260, 'z_index' => 100020]"
                        />
                        <x-field-error :messages="$errors->get('description')" class="mt-2" />
                    </div>

                    <div class="modal-action">
                        <x-button type="button" class="btn-ghost" wire:click="closeModal">
                            {{ __('Cancel') }}
                        </x-button>
                        <x-button type="submit" class="btn-primary">
                            {{ __('Save') }}
                        </x-button>
                    </div>
                </form>
            @endif
        </x-modal>
    </div>
</div>
