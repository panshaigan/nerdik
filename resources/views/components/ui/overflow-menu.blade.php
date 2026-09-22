@props([
    'icon',
    'label',
    'openUpward' => false,
    'panelClass' => 'w-56',
    'listDataUi' => null,
])

@php
    $triggerDataUi = $attributes->get('data-ui');
    $listDataUi = $listDataUi ?? (is_string($triggerDataUi) && $triggerDataUi !== '' ? $triggerDataUi.'-list' : null);
@endphp

<div
    {{ $attributes->except('data-ui')->class('relative') }}
    x-data="{
        open: false,
        openUpward: @js((bool) $openUpward),
        menuStyle: '',
        toggle() {
            if (this.open) {
                this.close();
                return;
            }

            this.$dispatch('ui-overflow-menu-open', { el: this.$el });
            this.open = true;
            this.$nextTick(() => {
                requestAnimationFrame(() => {
                    this.updatePosition();
                    requestAnimationFrame(() => this.updatePosition());
                });
            });
        },
        close() {
            this.open = false;
        },
        updatePosition() {
            const trigger = this.$refs.trigger;
            const panel = this.$refs.panel;
            if (! trigger || ! panel) {
                return;
            }

            const rect = trigger.getBoundingClientRect();
            const gap = 2;
            const panelWidth = panel.offsetWidth || 224;
            const panelHeight = panel.scrollHeight || panel.offsetHeight || 0;
            const maxLeft = Math.max(gap, window.innerWidth - panelWidth - gap);
            const left = Math.min(Math.max(gap, rect.right - panelWidth), maxLeft);
            const spaceBelow = window.innerHeight - rect.bottom - gap;
            const spaceAbove = rect.top - gap;
            const overflowsBelow = rect.bottom + gap + panelHeight > window.innerHeight;
            const openUpward = this.openUpward
                || (overflowsBelow && spaceAbove >= panelHeight)
                || (overflowsBelow && spaceAbove > spaceBelow);

            if (openUpward) {
                this.menuStyle = `position:fixed;left:${left}px;bottom:${window.innerHeight - rect.top + gap}px;top:auto;max-height:${Math.max(spaceAbove, 120)}px;overflow-y:auto;`;
            } else {
                this.menuStyle = `position:fixed;left:${left}px;top:${rect.bottom + gap}px;bottom:auto;max-height:${Math.max(spaceBelow, 120)}px;overflow-y:auto;`;
            }
        },
        onWindowClick(event) {
            if (! this.open) {
                return;
            }
            if (this.$refs.trigger?.contains(event.target) || this.$refs.panel?.contains(event.target)) {
                return;
            }
            this.close();
        },
    }"
    x-on:ui-overflow-menu-open.window="if ($event.detail.el !== $el) close()"
    x-on:keydown.escape.window="close()"
    x-on:click.window="onWindowClick($event)"
    x-on:resize.window="open && updatePosition()"
    x-on:scroll.window.passive="open && close()"
>
    <button
        type="button"
        x-ref="trigger"
        class="btn btn-ghost btn-square btn-sm text-base-content/80 hover:text-primary"
        x-on:click.stop="toggle()"
        :aria-expanded="open"
        aria-haspopup="menu"
        aria-label="{{ $label }}"
        @if (is_string($triggerDataUi) && $triggerDataUi !== '')
            data-ui="{{ $triggerDataUi }}"
        @endif
    >
        <x-icon :name="$icon" class="h-5 w-5" />
    </button>

    <template x-teleport="body">
        <ul
            x-ref="panel"
            x-bind:class="open ? 'flex' : 'hidden'"
            x-on:click="close()"
            role="menu"
            class="ui-overflow-menu-panel fixed z-[10000] flex-col gap-0.5 rounded-box border border-base-300 bg-base-100 p-2 light:border-neutral {{ $panelClass }}"
            :style="menuStyle"
            x-on:scroll.stop
            @if (is_string($listDataUi) && $listDataUi !== '')
                data-ui="{{ $listDataUi }}"
            @endif
        >
            {{ $slot }}
        </ul>
    </template>
</div>
