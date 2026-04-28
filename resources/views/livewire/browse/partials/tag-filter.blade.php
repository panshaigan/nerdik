{{-- Expects: $tags (collection), Livewire WithBrowseTagFilter — search + text chip only; toggles live in tag-filter-toggles partial above --}}
<div class="min-w-0 w-full max-w-full space-y-3 mt-2" data-ui="browse-tag-filter">
    <div wire:ignore class="block w-full min-w-0 max-w-full">
        @include('tags.partials.selector', [
            'tags' => $tags,
            'selectedIds' => $tag_ids,
            'allowCreate' => false,
            'browseTagSelector' => true,
            'placeholder' => __('ui.browse.tags_and_text_placeholder'),
            'browseTextSearch' => true,
        ])
    </div>

    @if (filled($q))
        <div class="flex flex-wrap gap-2" data-ui="browse-text-search-chip">
            <span
                class="inline-flex max-w-full items-center gap-1 rounded-full border border-secondary/40 bg-secondary/10 px-3 py-1 text-xs text-base-content"
                title="{{ __('ui.browse.text_search_chip_hint') }}"
            >
                <span class="sr-only">{{ __('ui.browse.text_search_chip_label') }}:</span>
                <span class="min-w-0 truncate">{{ $q }}</span>
                <x-button
                    type="button"
                    wire:click="clearTextSearch"
                    wire:loading.attr="disabled"
                    class="btn-ghost btn-xs btn-square min-h-0 h-5 w-5 shrink-0 p-0 opacity-70 hover:opacity-100"
                    :aria-label="__('ui.browse.text_search_remove')"
                >
                    ×
                </x-button>
            </span>
        </div>
    @endif
</div>
