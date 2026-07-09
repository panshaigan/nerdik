{{-- Expects: $tags (collection), Livewire WithBrowseTagFilter — search + text chip only; toggles live in tag-filter-toggles partial --}}
<div class="relative min-w-0 w-full max-w-full" data-ui="browse-tag-filter">
    <div wire:ignore class="block w-full min-w-0 max-w-full">
        @include('tags.partials.selector', [
            'tags' => $tags,
            'selectedIds' => $tag_ids,
            'allowCreate' => false,
            'browseTagSelector' => true,
            'browseDateRangeShellPadding' => true,
            'placeholder' => __('ui.browse.tags_and_text_placeholder'),
            'browseTextSearch' => true,
            'browseTextValue' => $q,
            'fieldShellClass' => $fieldShellClass ?? '',
        ])
    </div>
    <div
        class="pointer-events-none absolute inset-y-0 right-0 z-10 flex items-center pr-2"
        data-ui="browse-date-range-slot"
    >
        <div class="pointer-events-auto">
            @include('livewire.browse.partials.date-range-trigger', [
                'from_date' => $from_date ?? null,
                'to_date' => $to_date ?? null,
            ])
        </div>
    </div>
</div>
