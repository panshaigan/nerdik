<div class="py-12">
    <div class="mx-auto max-w-3xl sm:px-6 lg:px-8">
        @if (session('status'))
            <div role="status" class="alert alert-success mb-4 text-sm">
                {{ session('status') }}
            </div>
        @endif

        <div class="mb-6 flex justify-end">
            <button
                type="button"
                class="btn btn-primary btn-circle shadow-sm touch-manipulation"
                wire:click="openCreateModal"
                title="{{ __('Add organization') }}"
                aria-label="{{ __('Add organization') }}"
            >
                <svg class="h-6 w-6 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
            </button>
        </div>

        <ul class="space-y-3" role="list">
            @forelse ($organizations as $organization)
                <li
                    wire:key="org-{{ $organization->id }}"
                    class="flex items-start gap-3 rounded-lg border border-base-300 bg-base-100 p-4 shadow-sm"
                >
                    <div class="min-w-0 flex-1">
                        <p class="font-medium text-base-content">{{ $organization->name }}</p>
                        @if (filled(rich_text_excerpt($organization->desc)))
                            <div class="rich-text-content mt-2 text-sm text-base-content/80">
                                {!! rich_text($organization->desc) !!}
                            </div>
                        @endif
                    </div>
                    <div class="flex shrink-0 items-center gap-1">
                        <button
                            type="button"
                            class="btn btn-ghost btn-square btn-sm text-base-content/80 hover:text-primary"
                            wire:click="openEditModal({{ $organization->id }})"
                            title="{{ __('Edit') }}"
                            aria-label="{{ __('Edit') }}: {{ $organization->name }}"
                        >
                            <svg class="h-5 w-5 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125" />
                            </svg>
                        </button>
                        <button
                            type="button"
                            class="btn btn-ghost btn-square btn-sm text-base-content/80 hover:text-error"
                            wire:click="deleteOrganization({{ $organization->id }})"
                            wire:confirm="{{ __('Are you sure you want to delete this organization?') }}"
                            title="{{ __('Delete') }}"
                            aria-label="{{ __('Delete') }}: {{ $organization->name }}"
                        >
                            <svg class="h-5 w-5 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                            </svg>
                        </button>
                    </div>
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
                            wire:model.live="desc"
                            :label="__('Description (optional)')"
                            :gpl-license="true"
                            :config="['height' => 260, 'z_index' => 100020]"
                        />
                        <x-field-error :messages="$errors->get('desc')" class="mt-2" />
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
