{{-- Expects: $tags (collection), Livewire WithBrowseTagFilter — search + text chip only; toggles live in tag-filter-toggles partial --}}
<div class="min-w-0 w-full max-w-full" data-ui="browse-tag-filter">
    <div wire:ignore class="block w-full min-w-0 max-w-full">
        @include('tags.partials.selector', [
            'tags' => $tags,
            'selectedIds' => $tag_ids,
            'allowCreate' => false,
            'browseTagSelector' => true,
            'placeholder' => __('ui.browse.tags_and_text_placeholder'),
            'browseTextSearch' => true,
            'browseTextValue' => $q,
            'fieldShellClass' => $fieldShellClass ?? '',
        ])
    </div>
</div>
