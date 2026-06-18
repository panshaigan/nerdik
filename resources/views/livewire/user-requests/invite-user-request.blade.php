<div class="inline-flex" @if ($dataUi) data-ui="{{ $dataUi }}" @endif>
    @if ($this->usesIconTrigger())
        <x-button
            type="button"
            class="btn-ghost btn-square btn-sm text-base-content/80 hover:text-primary"
            wire:click="openModal"
            :title="$this->triggerTitle()"
            :aria-label="$this->triggerTitle()"
        >
            <x-mary-icon name="o-user-plus" class="h-5 w-5 shrink-0" />
        </x-button>
    @else
        <x-button
            type="button"
            class="btn-sm btn-outline"
            wire:click="openModal"
        >
            {{ $this->triggerLabel() }}
        </x-button>
    @endif

    @if ($modalOpen)
        <x-modal
            wire:model="modalOpen"
            :title="$this->modalTitle()"
            box-class="max-w-lg overflow-visible ui-modal-surface ui-invite-user-request-modal"
            class="backdrop-blur"
            separator
        >
            <div class="space-y-4">
                <div class="ui-invite-user-search">
                    @if ($selectedUserOption)
                        <div class="flex items-center gap-3 rounded-lg border border-base-300 bg-base-100 p-3">
                            <div class="avatar shrink-0">
                                <div class="h-10 w-10 overflow-hidden rounded-full">
                                    <img
                                        src="{{ $selectedUserOption['avatar'] }}"
                                        alt="{{ $selectedUserOption['name'] }}"
                                        class="h-full w-full object-cover"
                                    />
                                </div>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-medium text-base-content">{{ $selectedUserOption['name'] }}</p>
                                <p class="truncate text-sm text-base-content/60">{{ $selectedUserOption['display_name'] }}</p>
                            </div>
                            <x-button
                                type="button"
                                class="btn-ghost btn-square btn-sm shrink-0"
                                wire:click="clearSelectedUser"
                                icon="o-x-mark"
                                :title="__('ui.common.remove')"
                                :aria-label="__('ui.common.remove')"
                            />
                        </div>
                    @else
                        <div
                            class="relative"
                            x-data="inviteUserSearch(@js(collect($userOptions)->pluck('id')->values()->all()))"
                            @focusin="open = true"
                            @click.outside="open = false; activeIndex = -1"
                            @keydown.arrow-down.prevent="navigateDown()"
                            @keydown.arrow-up.prevent="navigateUp()"
                            @keydown.enter="onEnter($event)"
                            @keydown.escape="open = false; activeIndex = -1"
                        >
                            <x-input
                                wire:model.live.debounce.300ms="lastSearchTerm"
                                :label="__('ui.user_requests.invite_user_search_label')"
                                :placeholder="__('ui.user_requests.invite_user_search_placeholder')"
                                type="text"
                                autocomplete="off"
                                class="cursor-text"
                                aria-autocomplete="list"
                                aria-controls="invite-user-suggestions"
                                x-bind:aria-expanded="open && optionIds.length > 0 ? 'true' : 'false'"
                                x-bind:aria-activedescendant="activeDescendantId()"
                            />

                            @if (mb_strlen($lastSearchTerm) >= 2)
                                <div
                                    x-ref="suggestions"
                                    id="invite-user-suggestions"
                                    class="ui-invite-user-suggestions absolute left-0 right-0 z-20 mt-1 max-h-56 overflow-y-auto rounded-lg border border-base-300 bg-base-100 py-1 shadow-lg"
                                    role="listbox"
                                >
                                    <div
                                        wire:loading
                                        wire:target="lastSearchTerm,search,updatedLastSearchTerm"
                                        class="px-3 py-2 text-sm text-base-content/60"
                                    >
                                        {{ __('ui.common.loading') }}
                                    </div>

                                    @foreach ($userOptions as $option)
                                        <button
                                            type="button"
                                            wire:key="invite-option-{{ $option['id'] }}"
                                            wire:click="selectUser({{ $option['id'] }})"
                                            id="invite-user-option-{{ $loop->index }}"
                                            data-invite-option-index="{{ $loop->index }}"
                                            class="block w-full cursor-pointer text-left hover:bg-base-200"
                                            role="option"
                                            aria-selected="false"
                                        >
                                            <x-list-item
                                                :item="$option"
                                                value="name"
                                                sub-value="display_name"
                                                :no-hover="true"
                                                class="px-3 py-2"
                                            />
                                        </button>
                                    @endforeach

                                    @if (count($userOptions) === 0)
                                        <div
                                            wire:loading.remove
                                            wire:target="lastSearchTerm,search,updatedLastSearchTerm"
                                            class="px-3 py-2 text-sm text-base-content/60"
                                        >
                                            {{ __('ui.user_requests.invite_user_no_results') }}
                                        </div>
                                    @endif
                                </div>
                            @endif
                        </div>
                    @endif
                </div>

                <x-textarea
                    wire:model="message"
                    :label="__('ui.user_requests.optional_message')"
                    :placeholder="__('ui.user_requests.optional_message_placeholder')"
                    rows="3"
                />
            </div>

            <x-slot:actions>
                <x-button type="button" class="btn-ghost" wire:click="closeModal">{{ __('ui.common.cancel') }}</x-button>
                <x-button
                    type="button"
                    class="btn-primary"
                    wire:click="send"
                    wire:loading.attr="disabled"
                    x-bind:disabled="!$wire.selectedUserId"
                >
                    {{ __('ui.user_requests.send_invite') }}
                </x-button>
            </x-slot:actions>
        </x-modal>
    @endif
</div>
