@php
    use App\Models\TagCategory;

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
    $categories = TagCategory::query()
        ->with('translations')
        ->orderBy('key')
        ->get()
        ->map(fn (TagCategory $cat) => [
            'id' => (int) $cat->id,
            'name' => (string) $cat->name($locale),
        ])
        ->values()
        ->all();
    $categoryNamesById = collect($categories)->mapWithKeys(fn ($c) => [(int) $c['id'] => (string) $c['name']])->all();
    $tagsForJs = collect($tags ?? [])->map(function ($tag) use ($locale, $categoryNamesById) {
        $localeTranslation = collect($tag->translations ?? [])->firstWhere('locale', $locale);
        $fallbackTranslation = $localeTranslation ?: collect($tag->translations ?? [])->firstWhere('locale', 'en');
        $categoryId = (int) ($tag->tag_category_id ?? 0);
        $categoryName = (string) (($tag->tagCategory?->name($locale) ?? '') ?: ($categoryNamesById[$categoryId] ?? ''));
        return [
            'id' => (int) $tag->id,
            'category_id' => $categoryId,
            'category_name' => $categoryName,
            'slug' => (string) ($fallbackTranslation?->slug ?? ''),
            'labels' => collect($tag->translations ?? [])->mapWithKeys(fn ($t) => [(string) $t->locale => (string) $t->label])->all(),
            'aliases' => collect($tag->aliases ?? [])->pluck('alias')->filter()->map(fn ($a) => (string) $a)->values()->all(),
            'related_ids' => collect($tag->tagRelations ?? [])->pluck('related_tag_id')->map(fn ($id) => (int) $id)->values()->all(),
        ];
    })->values()->all();
    $skipLivewireSync = (bool) ($skipLivewireSync ?? false);
    $browseTagSelector = ($browseTagSelector ?? false) === true;
    $allowCreate = ($allowCreate ?? true) !== false;
    $tagInputPlaceholder = $placeholder ?? __('Type to search tags (or create a new one)');
    $browseTextSearch = ($browseTextSearch ?? false) === true;
    $tagSelectorConfig = [
        'locale' => $locale,
        'tags' => $tagsForJs,
        'categories' => $categories,
        'initialSelectedIds' => $selected,
        'initialNewTags' => $initialNewTags,
        'allowCreate' => $allowCreate,
        'strings' => [
            'createTag' => __('Create tag'),
            'auto' => __('auto'),
            'browseTextSearchHint' => __('ui.browse.text_search_chip_hint'),
            'browseTextSearchLabel' => __('ui.browse.text_search_chip_label'),
            'browseTextSearchRemove' => __('ui.browse.text_search_remove'),
        ],
    ];
    if ($browseTextSearch) {
        $tagSelectorConfig['browseTextSearch'] = [
            'enabled' => true,
            'property' => 'q',
            'value' => (string) ($browseTextValue ?? ''),
        ];
    }
@endphp

@if (empty($tagsForJs))
    <p class="text-sm text-base-content/70">{{ __('No tags in the system yet. Start typing to create the first ones.') }}</p>
@endif

<div
    data-tag-selector
    class="space-y-3"
    @if ($skipLivewireSync) data-ts-skip-livewire-sync="1" @endif
    @if ($browseTagSelector) data-browse-tag-selector @endif
>
    <script type="application/json" data-ts-config>
        @json($tagSelectorConfig)
    </script>
    <div class="relative z-[1000]">
        {{-- Unified shell: selected tag chips + input inside one bordered field, matching manage tags UX. --}}
        <label
            data-ts-field
            class="input input-bordered rounded-xl flex min-h-10 !h-auto w-full min-w-0 flex-wrap items-start gap-x-2 gap-y-1.5 py-2"
        >
            <div data-ts-chips class="flex w-fit max-w-full min-w-0 flex-wrap content-start items-start gap-1"></div>
            <x-input
                type="text"
                inputmode="search"
                enterkeyhint="search"
                data-ts-input
                class="min-w-[8rem] flex-1 basis-[8rem] self-center border-0 bg-transparent p-0 text-base shadow-none outline-none ring-0 placeholder:text-base-content/40 focus:border-0 focus:ring-0 focus:outline-none"
                placeholder="{{ $tagInputPlaceholder }}"
                autocomplete="off"
                icon="o-magnifying-glass"
            />
        </label>
        <div
            data-ts-results
            class="absolute left-0 right-0 top-full z-[1001] mt-1 hidden max-h-60 overflow-y-auto rounded-lg border border-base-300 bg-base-100 py-1 shadow-lg"
        ></div>
    </div>

    <div data-ts-new-wrap class="hidden space-y-2 rounded-lg border border-primary/30 bg-primary/5 p-3">
        <p class="text-xs font-medium text-base-content">{{ __('New tags to create') }}</p>
        <div data-ts-new class="space-y-2"></div>
    </div>

    <div data-ts-hidden-ids></div>
</div>
