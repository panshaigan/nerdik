@php
    $tabListAttributes = $attributes->filter(
        fn (mixed $_value, string $key): bool => ! str_starts_with($key, 'wire:model'),
    );
@endphp
<div
    x-data="{
            tabs: [],
            selected:
                @if ($selected)
                    '{{ $selected }}'
                @else
                    @entangle($attributes->wire('model'))
                @endif
        }"
    class="{{ $tabsClass }}"
    x-class="font-semibold pb-1 border-b-[length:var(--border)] border-b-base-content/50 border-b-base-content/10 flex overflow-x-auto scrollbar-hide relative w-full"
>
    <div class="{{ $labelBarClass }}">
        <div class="{{ $labelDivClass }} min-w-0 flex-1">
            <template x-for="tab in tabs" :key="tab.name">
                <button
                    type="button"
                    role="tab"
                    :data-tab-name="tab.name"
                    x-init="if (typeof tab == 'undefined') $el.remove()"
                    x-html="tab.label"
                    @click="tab.disabled ? null: selected = tab.name"
                    :class="{ '{{ $activeClass }} tab-active': selected === tab.name, 'hidden': tab.hidden }"
                    class="tab {{ $labelClass }} [&_.inline-flex>div:last-child]:hidden [&_.inline-flex>div:last-child]:sm:inline [&_.inline-flex>*:first-child]:max-sm:!me-0"
                ></button>
            </template>
        </div>
        @if (isset($toolbar) && ! $toolbar->isEmpty())
            <div class="{{ $toolbarWrapperClass }}">
                {{ $toolbar }}
            </div>
        @endif
    </div>

    <div role="tablist" {{ $tabListAttributes->class(['relative block']) }}>
        @isset($panelOverlay)
            {{ $panelOverlay }}
        @endisset
        {{ $slot }}
    </div>
</div>
