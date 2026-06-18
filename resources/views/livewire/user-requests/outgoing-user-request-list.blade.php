<div class="mt-8 border-t border-base-300 pt-6">
    <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-base-content/60">
        {{ __('ui.user_requests.outgoing_heading') }}
    </h3>

    @forelse ($requests as $request)
        <div class="mb-2 flex items-center justify-between gap-3 rounded-lg border border-base-300 px-3 py-2 text-sm">
            <div class="min-w-0">
                <p class="font-medium">{{ $request->type->label() }}</p>
                <p class="truncate text-base-content/70">
                    {{ $request->recipient?->displayName() ?? __('ui.user_requests.organizer_flag_subject') }}
                </p>
            </div>
            <x-button
                type="button"
                class="btn-ghost btn-xs shrink-0"
                wire:click="cancel({{ $request->id }})"
                wire:loading.attr="disabled"
            >
                {{ __('ui.user_requests.cancel_request') }}
            </x-button>
        </div>
    @empty
        <p class="text-sm text-base-content/60">{{ __('ui.user_requests.outgoing_empty') }}</p>
    @endforelse
</div>
