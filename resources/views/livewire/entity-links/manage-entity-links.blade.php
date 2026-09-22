@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\EntityLink> $links */
    $listClass = match ($appearance) {
        'compact' => 'flex flex-wrap items-center gap-x-3 gap-y-1 text-sm',
        'subtitle' => 'flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-base-content/80',
        'overlay' => 'flex flex-wrap items-center gap-x-3 gap-y-1 text-sm',
        default => 'flex flex-wrap items-center gap-x-4 gap-y-2 text-sm',
    };
    $linkClass = match ($appearance) {
        'overlay' => 'link link-hover text-white/90',
        default => 'link link-primary link-hover',
    };
@endphp

<div
    wire:key="manage-entity-links-{{ $listenerKey }}"
    data-ui="{{ $dataUi }}"
    data-entity-links-key="{{ $listenerKey }}"
>
    @if ($showList && ($links->isNotEmpty() || ($canManage && $showAddButton)))
        <div class="{{ $listClass }}" data-ui="{{ $dataUi }}-list">
            @foreach ($links as $link)
                <span
                    wire:key="entity-link-{{ $link->id }}"
                    class="inline-flex items-center gap-1.5"
                    data-ui="{{ $dataUi }}-item"
                >
                    <a
                        href="{{ $link->url }}"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="{{ $linkClass }}"
                        data-ui="{{ $dataUi }}-anchor"
                    >{{ $link->name }}</a>
                    @if ($canManage)
                        <button
                            type="button"
                            class="btn btn-ghost btn-xs px-1"
                            wire:click="openEditLinkModal({{ $link->id }})"
                            aria-label="{{ __('ui.entity_links.edit_action') }}"
                            data-ui="{{ $dataUi }}-edit"
                        >
                            <x-icon name="o-pencil" class="h-3.5 w-3.5" />
                        </button>
                        <button
                            type="button"
                            class="btn btn-ghost btn-xs px-1 text-error"
                            wire:click="confirmDeleteLink({{ $link->id }})"
                            aria-label="{{ __('ui.entity_links.remove_action') }}"
                            data-ui="{{ $dataUi }}-remove"
                        >
                            <x-icon name="o-trash" class="h-3.5 w-3.5" />
                        </button>
                    @endif
                </span>
            @endforeach

            @if ($canManage && $showAddButton)
                <button
                    type="button"
                    class="btn btn-outline btn-xs"
                    wire:click="openAddLinkModal"
                    data-ui="{{ $dataUi }}-add"
                >
                    <x-icon name="o-plus" class="h-3.5 w-3.5" />
                    {{ __('ui.entity_links.add_action') }}
                </button>
            @endif
        </div>
    @endif

    @auth
        @if ($canManage)
            <dialog
                id="{{ $modalId }}"
                class="modal backdrop-blur"
                data-ui="entity-links-modal"
                wire:ignore.self
            >
                <div class="modal-box max-w-lg ui-modal-surface">
                    <form method="dialog" tabindex="-1">
                        <button
                            type="submit"
                            class="btn btn-circle btn-sm btn-ghost absolute end-2 top-2 z-[999]"
                            aria-label="{{ __('ui.common.close') }}"
                            tabindex="-1"
                        >
                            <x-mary-icon name="o-x-mark" class="h-4 w-4" />
                        </button>
                    </form>

                    <h3 class="text-lg font-semibold pr-10">
                        {{ $editingLinkId ? __('ui.entity_links.edit_action') : __('ui.entity_links.add_action') }}
                    </h3>

                    <form wire:submit.prevent="saveLink" class="mt-4 space-y-4" data-ui="entity-links-form">
                        <div>
                            <x-input
                                wire:model="linkName"
                                :label="__('ui.entity_links.name')"
                                data-ui="entity-links-name"
                            />
                            <x-field-error :messages="$errors->get('linkName')" class="mt-1" />
                        </div>
                        <div>
                            <x-input
                                wire:model="linkUrl"
                                type="url"
                                :label="__('ui.entity_links.url')"
                                placeholder="https://"
                                data-ui="entity-links-url"
                            />
                            <x-field-error :messages="$errors->get('linkUrl')" class="mt-1" />
                        </div>

                        <div class="modal-action">
                            <x-button type="button" class="btn-outline" onclick="this.closest('dialog')?.close()">
                                {{ __('ui.common.cancel') }}
                            </x-button>
                            <x-button type="submit" class="btn-primary" data-ui="entity-links-save">
                                {{ __('ui.common.save') }}
                            </x-button>
                        </div>
                    </form>
                </div>
                <form method="dialog" class="modal-backdrop">
                    <button type="submit" class="btn-ghost" aria-label="{{ __('ui.common.cancel') }}">{{ __('ui.common.cancel') }}</button>
                </form>
            </dialog>

            <x-ui.confirm-modal
                wire:model="confirmDeleteOpen"
                :title="__('ui.entity_links.remove_action')"
                :message="__('ui.entity_links.remove_confirm')"
                confirm-action="deleteLink"
            />
        @endif
    @endauth
</div>
