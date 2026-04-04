{{-- Expects: $tags (collection), Livewire WithBrowseTagFilter --}}
<div class="min-w-0 w-full space-y-3" data-ui="browse-tag-filter">
    <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-base-content/80">
        <span class="whitespace-nowrap">{{ __('ui.browse.tags_match_any') }}</span>
        <input
            type="checkbox"
            wire:model.live="tags_match_all"
            class="toggle toggle-primary toggle-sm"
            data-ui="browse-tag-filter-match-mode"
        />
        <span class="whitespace-nowrap">{{ __('ui.browse.tags_match_all') }}</span>
    </div>

    <div wire:ignore>
        @include('tags.partials.selector', [
            'tags' => $tags,
            'selectedIds' => $tag_ids,
            'allowCreate' => false,
            'placeholder' => __('ui.browse.tags_search_placeholder'),
        ])
    </div>
</div>
