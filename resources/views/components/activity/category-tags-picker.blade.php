@props([
    'config' => [],
])

@php
    $cfg = is_array($config) ? $config : [];
@endphp

<div data-activity-tag-picker class="space-y-4">
    <script type="application/json" data-atp-config>@json($cfg)</script>
    @if (empty($cfg['tags'] ?? []))
        <p class="text-sm text-base-content/70">{{ __('No tags in the system yet. Start typing to create the first ones.') }}</p>
    @endif
    @foreach ($cfg['categories'] ?? [] as $cat)
        @php
            $cid = (int) ($cat['id'] ?? 0);
            $cname = (string) ($cat['name'] ?? '');
        @endphp
        @if ($cid > 0)
            <div class="atp-category-row space-y-1" data-atp-category-row data-category-id="{{ $cid }}">
                <fieldset class="fieldset py-0">
                    <legend class="fieldset-legend mb-0.5">{{ $cname }}</legend>
                    <div class="relative z-[1000]">
                        <label class="input input-bordered flex min-h-10 w-full min-w-0 flex-wrap items-center gap-2 py-1">
                            <div data-atp-chips class="flex flex-wrap content-center items-center gap-1"></div>
                            <input
                                type="text"
                                data-atp-input
                                class="min-w-[8rem] grow basis-16"
                                placeholder="{{ __('Type to search tags (or create a new one)') }}"
                                autocomplete="off"
                                inputmode="search"
                                enterkeyhint="search"
                            />
                        </label>
                        <div
                            data-atp-results
                            class="absolute left-0 right-0 top-full z-[1001] mt-1 hidden max-h-60 overflow-y-auto rounded-lg border border-base-300 bg-base-100 py-1 shadow-lg"
                        ></div>
                    </div>
                </fieldset>
            </div>
        @endif
    @endforeach
</div>
