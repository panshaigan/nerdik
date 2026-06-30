<div class="mb-8 border-b border-base-300 pb-6 px-4" data-ui="incoming-user-requests">
    <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-base-content/60">
        {{ __('ui.user_requests.incoming_heading') }}
    </h3>

    @forelse ($requests as $request)
        <div class="mb-3 flex items-center justify-between gap-4 rounded-lg border border-base-300 p-4 text-sm">
            <x-user-request.summary :request="$request" :message-clamp="2" />
            <x-button
                type="button"
                class="btn-primary btn-xs shrink-0 self-center"
                wire:click="respond({{ $request->id }})"
                wire:loading.attr="disabled"
            >
                {{ __('ui.user_requests.respond') }}
            </x-button>
        </div>
    @empty
        <p class="text-sm text-base-content/60">{{ __('ui.user_requests.incoming_empty') }}</p>
    @endforelse
</div>
