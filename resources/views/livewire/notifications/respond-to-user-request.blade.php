<div>
    @if ($open)
        <x-modal
            wire:model="open"
            :title="__('ui.user_requests.review_request')"
            box-class="max-w-lg overflow-x-hidden ui-modal-surface"
            class="backdrop-blur"
            separator
        >
            @if ($request)
                <div class="space-y-4">
                    <div class="flex flex-wrap items-center gap-x-1.5 gap-y-1 text-sm">
                        @if ($request->requester)
                            <x-user-badge :user="$request->requester" size="sm" class="min-w-0" />
                        @else
                            <span class="font-semibold">{{ __('ui.common.unknown_user') }}</span>
                        @endif
                        <span class="text-base-content/50">·</span>
                        <x-user-request.subject :request="$request" />
                    </div>

                    @if ($request->message)
                        <blockquote class="rounded-lg border border-base-300 bg-base-200/40 px-3 py-2 text-sm italic">
                            {{ $request->message }}
                        </blockquote>
                    @endif

                    @if ($request->expires_at)
                        <p class="text-xs text-base-content/60">
                            {{ __('ui.user_requests.expires_at', ['time' => $request->expires_at->diffForHumans()]) }}
                        </p>
                    @endif

                    @if ($request->type === \App\Enums\UserRequestType::ActivityInvite)
                        <p class="text-xs text-base-content/70">{{ __('ui.user_requests.activity_accept_hint') }}</p>
                    @endif

                    @if ($errorMessage)
                        <p class="text-sm text-error">{{ $errorMessage }}</p>
                    @endif

                    @if ($canRespond)
                        <div class="space-y-2">
                            <x-textarea
                                wire:model="declineNote"
                                :label="__('ui.user_requests.decline_note')"
                                rows="2"
                            />
                        </div>

                        <x-slot:actions>
                            <x-button type="button" class="btn-ghost" wire:click="closeModal">{{ __('ui.common.cancel') }}</x-button>
                            <x-button type="button" class="btn-outline btn-error" wire:click="decline" wire:loading.attr="disabled">
                                {{ __('ui.user_requests.decline') }}
                            </x-button>
                            <x-button type="button" class="btn-primary" wire:click="accept" wire:loading.attr="disabled">
                                {{ __('ui.user_requests.accept') }}
                            </x-button>
                        </x-slot:actions>
                    @else
                        <p class="text-sm text-base-content/70">{{ __('ui.user_requests.already_resolved') }}</p>
                        <x-slot:actions>
                            <x-button type="button" class="btn-primary" wire:click="closeModal">{{ __('ui.common.close') }}</x-button>
                        </x-slot:actions>
                    @endif
                </div>
            @endif
        </x-modal>
    @endif
</div>
