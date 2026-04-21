@props([
    'config' => [],
    /**
     * Optional override: list of TagCategory `key` strings defining display order.
     * When null, uses $defaultCategoryKeyOrder below.
     */
    'categoryOrder' => null,
    /**
     * Flex layout wrapping all category fields (e.g. four per line: default uses flex-wrap + basis).
     */
    'rowClass' => 'flex flex-wrap gap-4',
    /**
     * Width of each category column; default two per row from `sm` up with gap-4 parent.
     */
    'itemClass' => 'min-w-0 basis-full sm:basis-[calc(50%-0.5rem)]',
])

@php
    use App\Models\TagCategory;

    $cfg = is_array($config) ? $config : [];

    /**
     * Display order: this list is walked top-to-bottom. Keys must match `tag_categories.key`.
     * Default matches {@see TagCategory::DEFAULT_KEYS}; change here to override without touching the model.
     */
    $defaultCategoryKeyOrder = TagCategory::DEFAULT_KEYS;

    $orderKeys = is_array($categoryOrder) && $categoryOrder !== [] ? $categoryOrder : $defaultCategoryKeyOrder;

    $byKey = collect($cfg['categories'] ?? [])
        ->filter(fn ($c) => (int) ($c['id'] ?? 0) > 0 && (string) ($c['key'] ?? '') !== '')
        ->keyBy(fn ($c) => (string) $c['key']);

    /** Exact sequence from $orderKeys; any category not listed is appended, sorted by key. */
    $categoriesOrdered = collect();
    foreach ($orderKeys as $key) {
        $key = (string) $key;
        if ($byKey->has($key)) {
            $categoriesOrdered->push($byKey->get($key));
            $byKey->forget($key);
        }
    }
    $categoriesOrdered = $categoriesOrdered
        ->concat($byKey->sortKeys()->values())
        ->values();
@endphp

<div data-activity-tag-picker>
    <script type="application/json" data-atp-config>@json($cfg)</script>
    @if (empty($cfg['tags'] ?? []))
        <p class="mb-4 text-sm text-base-content/70">{{ __('No tags in the system yet. Start typing to create the first ones.') }}</p>
    @endif
    <div class="{{ $rowClass }}">
        @foreach ($categoriesOrdered as $cat)
            @php
                $cid = (int) ($cat['id'] ?? 0);
                $cname = (string) ($cat['name'] ?? '');
            @endphp
            @if ($cid > 0)
                <div
                    class="atp-category-row relative z-0 {{ $itemClass }}"
                    data-atp-category-row
                    data-category-id="{{ $cid }}"
                >
                    <fieldset class="fieldset py-0">
                        <legend class="fieldset-legend mb-0.5">{{ $cname }}</legend>
                        <div class="relative w-full min-w-0 max-w-full">
                            {{-- Use a div, not <label>: a label would delegate clicks to the first focusable child (chip × buttons). --}}
                            <div
                                class="input input-bordered flex min-h-10 !h-auto w-full min-w-0 flex-wrap items-start gap-x-2 gap-y-1.5 py-2"
                                data-atp-field
                                role="group"
                            >
                                {{-- Chips: content-width only. `flex-1` here stole the row and captured clicks meant for the input. --}}
                                <div
                                    data-atp-chips
                                    class="flex w-fit max-w-full min-w-0 flex-wrap content-start items-start gap-1"
                                ></div>
                                <input
                                    type="text"
                                    data-atp-input
                                    class="min-w-[8rem] flex-1 basis-[8rem] self-center"
                                    placeholder="{{ __('Type to search tags (or create a new one)') }}"
                                    autocomplete="off"
                                    inputmode="search"
                                    enterkeyhint="search"
                                />
                            </div>
                            <div
                                data-atp-results
                                class="absolute left-0 right-0 top-full z-10 mt-1 hidden max-h-60 isolate overflow-y-auto rounded-lg border border-base-300 bg-base-100 text-base-content shadow-2xl ring-1 ring-base-300/80 [background-color:var(--color-base-100)] py-1"
                            ></div>
                        </div>
                    </fieldset>
                </div>
            @endif
        @endforeach
    </div>
</div>
