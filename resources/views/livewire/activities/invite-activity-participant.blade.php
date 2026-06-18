<div class="inline-flex">
    <x-button
        type="button"
        class="btn-sm btn-outline"
        wire:click="openModal"
        data-ui="activity-invite-participant"
    >
        {{ __('ui.user_requests.invite_participant') }}
    </x-button>

    @if ($modalOpen)
        <x-modal
            wire:model="modalOpen"
            :title="__('ui.user_requests.invite_participant_title')"
            box-class="max-w-lg overflow-x-hidden ui-modal-surface"
            class="backdrop-blur"
            separator
        >
            <div class="space-y-4">
                <x-input
                    wire:model.live.debounce.300ms="searchTerm"
                    :label="__('ui.user_requests.invite_participant_search_label')"
                    :placeholder="__('ui.user_requests.invite_participant_search_placeholder')"
                    icon="o-magnifying-glass"
                />

                @if (mb_strlen(trim($searchTerm)) >= 2)
                    <div class="max-h-48 space-y-1 overflow-y-auto rounded-lg border border-base-300 p-2">
                        @forelse ($searchResults as $user)
                            <button
                                type="button"
                                wire:click="selectUser({{ $user->id }})"
                                @class([
                                    'flex w-full items-center justify-between gap-2 rounded-md px-3 py-2 text-left text-sm transition-colors',
                                    'bg-primary/10 ring-1 ring-primary' => $selectedUserId === $user->id,
                                    'hover:bg-base-200' => $selectedUserId !== $user->id,
                                ])
                            >
                                <span class="font-medium">{{ $user->nickname }}</span>
                                <span class="truncate text-base-content/70">{{ $user->displayName() }}</span>
                            </button>
                        @empty
                            <p class="px-2 py-3 text-center text-sm text-base-content/60">
                                {{ __('ui.user_requests.invite_participant_no_results') }}
                            </p>
                        @endforelse
                    </div>
                @endif

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
                    :disabled="$selectedUserId === null"
                >
                    {{ __('ui.user_requests.send_invite') }}
                </x-button>
            </x-slot:actions>
        </x-modal>
    @endif
</div>
