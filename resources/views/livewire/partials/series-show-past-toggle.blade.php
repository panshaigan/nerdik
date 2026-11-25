@if ($hasPast)
    <div class="mb-4 flex flex-wrap items-center gap-3" data-ui="{{ $dataUi }}-wrapper">
        <x-button
            type="button"
            wire:click="toggleShowPast"
            wire:loading.attr="disabled"
            wire:target="toggleShowPast"
            class="btn-outline btn-sm btn-neutral"
            :aria-label="$showPast ? $hideLabel : $showLabel"
            data-ui="{{ $dataUi }}"
        >
            <span wire:loading.remove wire:target="toggleShowPast">
                {{ $showPast ? $hideLabel : $showLabel }}
            </span>
            <span wire:loading wire:target="toggleShowPast" class="inline-flex items-center gap-2">
                <span class="loading loading-spinner loading-xs" aria-hidden="true"></span>
                {{ __('ui.common.loading') }}
            </span>
        </x-button>
    </div>
@endif