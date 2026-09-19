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
            this.open = ! this.open;
            if (this.open) {
                this.$nextTick(() => {
                    this.updatePosition();
                    requestAnimationFrame(() => this.updatePosition());
                });
            }
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
            const gap = 8;
            const panelWidth = panel.offsetWidth || 224;
            const maxLeft = Math.max(gap, window.innerWidth - panelWidth - gap);
            const left = Math.min(Math.max(gap, rect.right - panelWidth), maxLeft);

            if (this.openUpward) {
                this.menuStyle = `position:fixed;left:${left}px;bottom:${window.innerHeight - rect.top + gap}px;top:auto;`;
            } else {
                this.menuStyle = `position:fixed;left:${left}px;top:${rect.bottom + gap}px;bottom:auto;`;
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
        title="{{ $label }}"
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
            class="fixed z-[10000] flex-col gap-0.5 rounded-box border border-base-300 bg-base-100 p-2 shadow-lg light:border-neutral {{ $panelClass }}"
            :style="menuStyle"
            @if (is_string($listDataUi) && $listDataUi !== '')
                data-ui="{{ $listDataUi }}"
            @endif
        >
            {{ $slot }}
        </ul>
    </template>
</div>
