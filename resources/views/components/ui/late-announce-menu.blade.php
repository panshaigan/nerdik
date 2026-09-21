@props([
    'currentMinutes' => null,
    'targetUserId' => null,
    'wireMethod' => 'announceLate',
    'openUpward' => false,
])

@php
    $resolvedCurrent = $currentMinutes !== null ? (int) $currentMinutes : null;
    $triggerDataUi = $attributes->get('data-ui');
    $hasLate = $resolvedCurrent !== null && $resolvedCurrent > 0;
    $label = $hasLate
        ? __('ui.activities.late_announce_update')
        : __('ui.activities.late_announce');
    $wireCallSet = $targetUserId !== null
        ? 'this.$wire.'.$wireMethod.'('.(int) $targetUserId.', value)'
        : 'this.$wire.'.$wireMethod.'(value)';
    $wireCallClear = $targetUserId !== null
        ? 'this.$wire.'.$wireMethod.'('.(int) $targetUserId.', null)'
        : 'this.$wire.'.$wireMethod.'(null)';
@endphp

<div
    {{ $attributes->except('data-ui')->class('relative inline-flex') }}
    x-data="{
        open: false,
        openUpward: @js((bool) $openUpward),
        minutes: @js($resolvedCurrent ?? 15),
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
            const panelWidth = panel.offsetWidth || 280;
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
                this.menuStyle = `position:fixed;left:${left}px;bottom:${window.innerHeight - rect.top + gap}px;top:auto;max-height:${Math.max(spaceAbove, 160)}px;overflow-y:auto;`;
            } else {
                this.menuStyle = `position:fixed;left:${left}px;top:${rect.bottom + gap}px;bottom:auto;max-height:${Math.max(spaceBelow, 160)}px;overflow-y:auto;`;
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
        confirm() {
            const value = Number.parseInt(this.minutes, 10);
            if (! Number.isFinite(value) || value < 1 || value > 240) {
                return;
            }
            {{ $wireCallSet }};
            this.close();
        },
        clearLate() {
            {{ $wireCallClear }};
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
        @class([
            'btn btn-ghost btn-square btn-sm text-base-content/80 hover:text-warning tooltip tooltip-bottom',
            'text-warning' => $hasLate,
        ])
        x-on:click.stop="toggle()"
        :aria-expanded="open"
        aria-haspopup="dialog"
        aria-label="{{ $label }}"
        data-tip="{{ $label }}"
        @if (is_string($triggerDataUi) && $triggerDataUi !== '')
            data-ui="{{ $triggerDataUi }}"
        @endif
    >
        <x-icon name="o-clock" class="h-5 w-5" />
    </button>

    <template x-teleport="body">
        <div
            x-ref="panel"
            x-show="open"
            x-cloak
            role="dialog"
            aria-label="{{ $label }}"
            class="ui-late-announce-panel fixed z-[10000] flex w-[min(18rem,calc(100vw-1.5rem))] flex-col gap-3 rounded-box border border-base-300 bg-base-100 p-3 shadow-lg light:border-neutral"
            :style="menuStyle"
            x-on:scroll.stop
            @if (is_string($triggerDataUi) && $triggerDataUi !== '')
                data-ui="{{ $triggerDataUi }}-panel"
            @endif
        >
            <label class="form-control w-full">
                <span class="label-text mb-1 text-xs font-medium text-base-content/70">{{ __('ui.activities.late_minutes_label') }}</span>
                <input
                    type="number"
                    min="1"
                    max="240"
                    step="1"
                    inputmode="numeric"
                    class="input input-bordered input-sm w-full"
                    x-model="minutes"
                    x-on:keydown.enter.prevent="confirm()"
                    data-ui="late-announce-minutes-input"
                />
            </label>
            <div class="flex flex-wrap items-center justify-end gap-2">
                @if ($hasLate)
                    <button
                        type="button"
                        class="btn btn-ghost btn-sm"
                        x-on:click.stop="clearLate()"
                        data-ui="late-announce-clear"
                    >
                        {{ __('ui.activities.late_clear') }}
                    </button>
                @endif
                <button
                    type="button"
                    class="btn btn-primary btn-sm"
                    x-on:click.stop="confirm()"
                    data-ui="late-announce-confirm"
                >
                    {{ __('ui.common.confirm') }}
                </button>
            </div>
        </div>
    </template>
</div>
