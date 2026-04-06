@php
    use App\Services\TagSelectionService;

    $locale = app()->getLocale();
    $selected = collect(old('tag_ids', $selectedIds ?? []))
        ->map(fn ($id) => (int) $id)
        ->filter()
        ->values()
        ->all();
    $oldNewTags = old('new_tags', []);
    $initialNewTags = collect(is_array($oldNewTags) ? $oldNewTags : [])
        ->filter(fn ($row) => is_array($row) && trim((string) ($row['label'] ?? '')) !== '' && trim((string) ($row['category'] ?? '')) !== '')
        ->map(fn ($row) => [
            'label' => trim((string) $row['label']),
            'category' => trim((string) $row['category']),
        ])
        ->values()
        ->all();
    $categories = TagSelectionService::CATEGORY_OPTIONS;
    $tagsForJs = collect($tags ?? [])->map(function ($tag) {
        return [
            'id' => (int) $tag->id,
            'category' => (string) $tag->category,
            'slug' => (string) $tag->slug,
            'labels' => collect($tag->translations ?? [])->mapWithKeys(fn ($t) => [(string) $t->locale => (string) $t->label])->all(),
            'aliases' => collect($tag->aliases ?? [])->pluck('alias')->filter()->map(fn ($a) => (string) $a)->values()->all(),
            'attached_ids' => collect($tag->tagRelations ?? [])->pluck('attached_tag_id')->map(fn ($id) => (int) $id)->values()->all(),
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
        ],
    ];
    if ($browseTextSearch) {
        $tagSelectorConfig['browseTextSearch'] = [
            'enabled' => true,
            'property' => 'q',
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
        {{-- Same structure as Mary <x-input>: label.input wraps the native input so DaisyUI border + focus ring match other fields. --}}
        <label class="input input-bordered flex w-full min-w-0 items-center gap-2">
            <input
                type="text"
                inputmode="search"
                enterkeyhint="search"
                data-ts-input
                class="min-w-0 grow basis-0"
                placeholder="{{ $tagInputPlaceholder }}"
                autocomplete="off"
            />
        </label>
        <div
            data-ts-results
            class="absolute left-0 right-0 top-full z-[1001] mt-1 hidden max-h-60 overflow-y-auto rounded-lg border border-base-300 bg-base-100 py-1 shadow-lg"
        ></div>
    </div>

    <div data-ts-chips class="flex min-h-[1.5rem] flex-wrap gap-2"></div>

    <div data-ts-new-wrap class="hidden space-y-2 rounded-lg border border-primary/30 bg-primary/5 p-3">
        <p class="text-xs font-medium text-base-content">{{ __('New tags to create') }}</p>
        <div data-ts-new class="space-y-2"></div>
    </div>

    <div data-ts-hidden-ids></div>
</div>
