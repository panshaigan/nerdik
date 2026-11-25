<div class="hidden" aria-hidden="true">
    @if ($modalOpen)
        @teleport('body')
            <x-modal
                wire:model="modalOpen"
                :title="$this->modalTitle()"
                box-class="overflow-x-hidden ui-modal-surface ui-overlay-shell ui-overlay-sheet"
                class="backdrop-blur modal-bottom md:modal-end"
                separator
                data-ui="overlay-sheet page-alias-modal"
            >
                <div class="space-y-4">
                    <x-input
                        :value="$targetPath"
                        :label="__('ui.page_aliases.target_path')"
                        readonly
                        disabled
                    />

                    <x-input
                        wire:model.live="alias"
                        :label="__('ui.page_aliases.alias')"
                        :hint="__('ui.page_aliases.alias_hint')"
                        placeholder="my-short-link"
                        prefix="/"
                    />

                    @if (trim($alias) !== '')
                        <p class="text-sm text-base-content/70">
                            {{ __('ui.page_aliases.preview', ['url' => $this->previewUrl()]) }}
                        </p>
                    @endif
                </div>

                <x-slot:actions>
                    <x-button type="button" class="btn-ghost" wire:click="closeModal">{{ __('ui.common.cancel') }}</x-button>
                    <x-button type="button" class="btn-primary" wire:click="submit" wire:loading.attr="disabled">
                        {{ $this->submitLabel() }}
                    </x-button>
                </x-slot:actions>
            </x-modal>
        @endteleport
    @endif
</div>