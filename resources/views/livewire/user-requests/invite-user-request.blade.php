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
                <div
                    class="ui-invite-user-choices"
                    data-has-user-options="{{ count($userOptions) > 0 ? '1' : '0' }}"
                    wire:key="invite-choices-{{ $lastSearchTerm }}-{{ count($userOptions) }}"
                >
                    <x-choices
                        wire:model.live="selectedUserId"
                        :options="$userOptions"
                        option-value="id"
                        option-label="name"
                        option-sub-label="display_name"
                        option-avatar="avatar"
                        searchable
                        single
                        :min-chars="2"
                        debounce="300ms"
                        :label="__('ui.user_requests.invite_user_search_label')"
                        :placeholder="__('ui.user_requests.invite_user_search_placeholder')"
                        :no-result-text="__('ui.user_requests.invite_user_no_results')"
                    />
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
