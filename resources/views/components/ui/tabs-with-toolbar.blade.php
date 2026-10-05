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
            hostId: 'tabs-' + Math.random().toString(36).slice(2, 9),
            rootEl: null,
            init() {
                this.rootEl = this.$el;
                this.$nextTick(() => this.syncTabAria());

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
            },
            syncTabAria() {
                const root = this.rootEl || this.$el;

                this.tabs.forEach((tab) => {
                    const button = root.querySelector('[role=tab][data-tab-name=\'' + tab.name + '\']');
                    const panel = root.querySelector('[role=tabpanel][data-tab-panel=\'' + tab.name + '\']');

                    if (! button || ! panel) {
                        return;
                    }

                    const buttonId = this.hostId + '-tab-' + tab.name;
                    const panelId = this.hostId + '-tab-panel-' + tab.name;
                    button.id = buttonId;
                    button.setAttribute('aria-controls', panelId);
                    panel.id = panelId;
                    panel.setAttribute('aria-labelledby', buttonId);
                });
            },
            plainTabLabel(tab) {
                if (! tab?.label) {
                    return '';
                }

                const el = document.createElement('div');
                el.innerHTML = tab.label;

                return (el.textContent || '').replace(/\s+/g, ' ').trim();
            }
        }"
    class="{{ $tabsClass }}"
>
    <div {{ $tabListAttributes->class(['flex min-h-0 flex-1 flex-col']) }}>
    {{-- Chrome must be a direct sibling of [data-ui=tabs-panels] so sticky's parent is the tall column. --}}
    <div data-ui="tabs-toolbar-chrome">
        @isset($heading)
            {{ $heading }}
        @endisset
        <div class="{{ $labelBarClass }}">
            <div
                class="{{ $labelDivClass }} @container min-w-0 flex-1"
                role="tablist"
                aria-label="{{ __('ui.common.tabs') }}"
                x-effect="syncTabAria()"
            >
                <template x-for="tab in tabs" :key="tab.name">
                    <button
                        type="button"
                        role="tab"
                        :aria-selected="typeof selected !== 'undefined' && selected === tab.name"
                        :tabindex="typeof selected !== 'undefined' && selected === tab.name ? 0 : -1"
                        :data-tab-name="tab.name"
                        :data-tip="plainTabLabel(tab)"
                        :aria-label="plainTabLabel(tab)"
                        x-init="if (typeof tab == 'undefined') { $el.remove() } else { syncTabAria() }"
                        x-html="tab.label"
                        @click="tab.disabled ? null: selectTab(tab.name)"
                        :class="{ '{{ $activeClass }} tab-active': typeof selected !== 'undefined' && selected === tab.name, 'hidden': tab.hidden }"
                        class="tab {{ $labelClass }} [&_.inline-flex>div:last-child]:hidden @2xl:[&_.inline-flex>div:last-child]:inline @max-2xl:[&_.inline-flex>*:first-child]:!me-0 @max-2xl:tooltip @max-2xl:tooltip-top"
                    ></button>
                </template>
            </div>
            @if (isset($toolbar) && ! $toolbar->isEmpty())
                <div class="{{ $toolbarWrapperClass }}">
                    {{ $toolbar }}
                </div>
            @endif
        </div>
    </div>

    <div data-ui="tabs-panels" class="relative block">
        @isset($panelOverlay)
            {{ $panelOverlay }}
        @endisset
        {{ $slot }}
    </div>
    </div>
</div>
