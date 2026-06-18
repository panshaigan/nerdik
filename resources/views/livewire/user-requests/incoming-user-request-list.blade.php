<div class="mb-8 border-b border-base-300 pb-6" data-ui="incoming-user-requests">
    <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-base-content/60">
        {{ __('ui.user_requests.incoming_heading') }}
    </h3>

    @forelse ($requests as $request)
        <div class="mb-2 flex items-center justify-between gap-3 rounded-lg border border-base-300 px-3 py-2 text-sm">
            <div class="min-w-0">
                <p class="font-medium">{{ $request->type->label() }}</p>
                <div class="flex flex-wrap items-center gap-x-1.5 gap-y-1 truncate text-base-content/70">
                    @if ($request->requester)
                        <x-user-badge :user="$request->requester" size="sm" class="min-w-0" />
                    @else
                        <span>{{ __('ui.common.unknown_user') }}</span>
                    @endif
                    <span class="text-base-content/50">·</span>
                    <x-user-request.subject :request="$request" />
                </div>
                @if ($request->message)
                    <p class="mt-1 line-clamp-2 text-xs text-base-content/60 italic">
                        {{ $request->message }}
                    </p>
                @endif
                @if ($request->expires_at)
                    <p class="mt-1 text-xs text-base-content/50">
                        {{ __('ui.user_requests.expires_at', ['time' => $request->expires_at->diffForHumans()]) }}
                    </p>
                @endif
            </div>
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
