{{-- Tag match mode selector; search and listing filters are rendered in sibling partials. --}}
<div data-ui="browse-tag-filter-toggles">
    <x-group
        wire:model.live="tags_match_all"
        data-ui="browse-tag-filter-match-mode"
        class=""
        label="Match"
        :options="[
            ['id' => 0, 'name' => __('ui.browse.tags_match_any')],
            ['id' => 1, 'name' => __('ui.browse.tags_match_all')],
        ]"
    />
</div>
