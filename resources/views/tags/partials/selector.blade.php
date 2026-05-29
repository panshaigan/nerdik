@php
    use App\Support\Browse\BrowseTagSelectorPayload;

    $locale = app()->getLocale();
    $selected = collect(old('tag_ids', $selectedIds ?? []))
        ->map(fn ($id) => (int) $id)
        ->filter()
        ->values()
        ->all();
    $oldNewTags = old('new_tags', []);
    $initialNewTags = collect(is_array($oldNewTags) ? $oldNewTags : [])
        ->filter(fn ($row) => is_array($row) && trim((string) ($row['label'] ?? '')) !== '' && (int) ($row['category_id'] ?? 0) > 0)
        ->map(fn ($row) => [
            'label' => trim((string) $row['label']),
            'category_id' => (int) $row['category_id'],
        ])
        ->values()
        ->all();
    $categories = BrowseTagSelectorPayload::categoriesForLocale($locale);
    $categoryMaps = BrowseTagSelectorPayload::categoryMapsFromConfig($categories);
    $browseTagSelector = ($browseTagSelector ?? false) === true;
    $tagsForJs = BrowseTagSelectorPayload::fromCollection(
        $tags ?? [],
        $locale,
        $categoryMaps['namesById'],
        $categoryMaps['keysById'],
        includeRelatedIds: ! $browseTagSelector,
    );
    $skipLivewireSync = (bool) ($skipLivewireSync ?? false);
    $allowCreate = ($allowCreate ?? true) !== false;
    $tagInputPlaceholder = $placeholder ?? __('ui.tags.placeholder_search_or_create');
    $browseTextSearch = ($browseTextSearch ?? false) === true;
    $fieldShellClass = trim((string) ($fieldShellClass ?? ''));
    $fieldShellUsesBrandFrame = $browseTagSelector && $fieldShellClass !== '';
    $tagSelectorConfig = [
        'locale' => $locale,
        'tags' => $tagsForJs,
        'categories' => $categories,
        'initialSelectedIds' => $selected,
        'initialNewTags' => $initialNewTags,
        'allowCreate' => $allowCreate,
        'strings' => [
            'createTag' => __('ui.tags.create_tag'),
            'auto' => __('ui.tags.auto'),
            'browseTextSearchHint' => __('ui.browse.text_search_chip_hint'),
            'browseTextSearchLabel' => __('ui.browse.text_search_chip_label'),
            'browseTextSearchRemove' => __('ui.browse.text_search_remove'),
            'browseSuggestionsLoading' => __('ui.browse.suggestions_loading'),
            'browseSuggestionsEvents' => __('ui.browse.suggestions_events'),
            'browseSuggestionsActivities' => __('ui.browse.suggestions_activities'),
        ],
    ];
    if ($browseTextSearch) {
        $tagSelectorConfig['browseTextSearch'] = [
            'enabled' => true,
            'property' => 'q',
            'value' => (string) ($browseTextValue ?? ''),
        ];
    }
    if ($browseTagSelector) {
        $tagSelectorConfig['browseSuggestions'] = [
            'categoryOrder' => config('browse.tag_suggestions.category_order', []),
            'hiddenCategoryKeysOnEmptySearch' => config('browse.tag_suggestions.hidden_category_keys_on_empty_search', []),
            'maxPerCategory' => (int) config('browse.tag_suggestions.max_per_category', 7),
            'searchLimit' => (int) config('browse.tag_suggestions.search_limit', 30),
        ];
    }
@endphp

@if (empty($tagsForJs))
    <p class="text-sm text-base-content/70">{{ __('ui.tags.empty_system') }}</p>
@endif

<div
    data-tag-selector
    class=""
    @if ($skipLivewireSync) data-ts-skip-livewire-sync="1" @endif
    @if ($browseTagSelector) data-browse-tag-selector @endif
>
    <script type="application/json" data-ts-config>
        @json($tagSelectorConfig)
    </script>
    <div @class(['relative', 'z-[50]' => ! $browseTagSelector])>
        {{-- Unified shell: selected tag chips + input inside one bordered field, matching manage tags UX. --}}
        <div
            data-ts-field
            @class([
                'input rounded-xl flex min-h-10 !h-auto w-full min-w-0 flex-wrap items-start gap-x-2 gap-y-1.5',
                'input-bordered' => ! $fieldShellUsesBrandFrame,
                $fieldShellClass => $fieldShellClass !== '',
            ])
        >
            <div data-ts-chips class="flex w-fit max-w-full min-w-0 flex-wrap content-start items-start gap-1"></div>
            <x-input
                type="text"
                inputmode="search"
                enterkeyhint="search"
                data-ts-input
                data-ts-placeholder="{{ $tagInputPlaceholder }}"
                class="min-w-[8rem] flex-1 basis-[8rem] self-center border-0 bg-transparent p-0 text-base shadow-none outline-none ring-0 placeholder:text-base-content/40 focus:border-0 focus:ring-0 focus:outline-none"
                placeholder="{{ $tagInputPlaceholder }}"
                autocomplete="off"
                icon="o-magnifying-glass"
            />
        </div>
        <div
            data-ts-results
            @class([
                'absolute left-0 right-0 top-full mt-1 hidden rounded-2xl border border-base-300 bg-base-100 shadow-lg',
                'z-30 overflow-visible py-1' => $browseTagSelector,
                'z-[100] max-h-60 overflow-y-auto py-1' => ! $browseTagSelector,
            ])
        ></div>
    </div>

    <div data-ts-new-wrap class="hidden space-y-2 rounded-2xl border border-primary/30 bg-primary/5 p-3">
        <p class="text-xs font-medium text-base-content">{{ __('ui.tags.new_to_create') }}</p>
        <div data-ts-new class="space-y-2"></div>
    </div>

    <div data-ts-hidden-ids></div>
</div>
