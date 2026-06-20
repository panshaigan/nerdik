<div>
    @if ($open)
        <x-modal
            wire:model="open"
            :title="__('ui.user_requests.review_request')"
            box-class="max-w-lg overflow-x-hidden ui-modal-surface"
            class="backdrop-blur"
            separator
        >
            @if ($modalState === 'actionable' && $request)
                <div class="space-y-4">
                    <x-user-request.summary :request="$request" />

                    @if ($request->type === \App\Enums\UserRequestType::ActivityInvite)
                        <p class="text-xs text-base-content/70">{{ __('ui.user_requests.activity_accept_hint') }}</p>
                    @endif

                    @if ($errorMessage)
                        <p class="text-sm text-error">{{ $errorMessage }}</p>
                    @endif

                    <div class="space-y-2">
                        <x-textarea
                            wire:model="declineNote"
                            :label="__('ui.user_requests.decline_note')"
                            rows="2"
                        />
                    </div>

                    <x-slot:actions>
                        <x-button type="button" class="btn-outline btn-error" wire:click="decline" wire:loading.attr="disabled">
                            {{ __('ui.user_requests.decline') }}
                        </x-button>
                        <x-button type="button" class="btn-primary" wire:click="accept" wire:loading.attr="disabled">
                            {{ __('ui.user_requests.accept') }}
                        </x-button>
                    </x-slot:actions>
                </div>
            @elseif ($modalState === 'already_resolved' && $request)
                <div class="space-y-4">
                    <x-user-request.summary :request="$request" />
                    <p class="text-sm text-base-content/70">{{ $resolvedMessage }}</p>
                    <x-slot:actions>
                        <x-button type="button" class="btn-primary" wire:click="closeModal">{{ __('ui.common.close') }}</x-button>
                    </x-slot:actions>
                </div>
            @else
                <div class="space-y-4">
                    <p class="text-sm text-base-content/70">
                        @switch($modalState)
                            @case('expired')
                                {{ __('ui.user_requests.expired') }}
                                @break
                            @case('unauthorized')
                                {{ __('ui.user_requests.respond_unauthorized') }}
                                @break
                            @default
                                {{ __('ui.user_requests.invalid_request') }}
                        @endswitch
                    </p>
                    <x-slot:actions>
                        <x-button type="button" class="btn-primary" wire:click="closeModal">{{ __('ui.common.close') }}</x-button>
                    </x-slot:actions>
                </div>
            @endif
        </x-modal>
    @endif
</div>
