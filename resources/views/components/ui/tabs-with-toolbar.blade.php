@php
    $tabListAttributes = $attributes->filter(
        fn (mixed $_value, string $key): bool => ! str_starts_with($key, 'wire:model'),
    );
    $preserveScroll = $selected
        ? false
        : $attributes->wire('model')->hasModifier('preserve-scroll');
@endphp
<div
    @if ($preserveScroll)
        data-preserve-scroll
    @endif
    x-data="{
            tabs: [],
            selected:
                @if ($selected)
                    '{{ $selected }}'
                @else
                    @entangle($attributes->wire('model'))
                @endif,
            preserveScroll: {{ $preserveScroll ? 'true' : 'false' }},
            restoreScroll: null,
            init() {
                if (! this.preserveScroll) {
                    return;
                }

                this.$wire.interceptMessage(({ onSuccess, onFinish }) => {
                    if (this.restoreScroll === null) {
                        return;
                    }

                    const restore = this.restoreScroll;

                    onSuccess(({ onMorph, onRender }) => {
                        onMorph(restore);
                        onRender(restore);
                    });
                    onFinish(() => {
                        restore();
                        this.restoreScroll = null;
                    });
                });
            },
            selectTab(name) {
                if (this.preserveScroll) {
                    const y = window.scrollY;
                    const x = window.scrollX;
                    this.restoreScroll = () => window.scrollTo({ top: y, left: x, behavior: 'instant' });
                }

                this.selected = name;
            }
        }"
    class="{{ $tabsClass }}"
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
                    @click="tab.disabled ? null: selectTab(tab.name)"
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
