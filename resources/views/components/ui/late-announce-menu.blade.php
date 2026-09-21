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
    $presets = [5, 10, 15, 20, 30, 45];
    $isCustomCurrent = $resolvedCurrent !== null && ! in_array($resolvedCurrent, $presets, true);
    $unitShort = __('ui.activities.late_minutes_unit_short');
    $safeWireMethod = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $wireMethod) ?: 'announceLate';
    $inputId = 'late-announce-custom-'.preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($triggerDataUi ?? 'default'));
@endphp

<div
    {{ $attributes->except('data-ui')->class('relative inline-flex') }}
    x-data="{
        open: false,
        openUpward: {{ $openUpward ? 'true' : 'false' }},
        minutes: {{ (int) ($resolvedCurrent ?? 15) }},
        custom: '{{ $isCustomCurrent ? (int) $resolvedCurrent : '' }}',
        menuStyle: '',
        presets: [5, 10, 15, 20, 30, 45],
        unitShort: '{{ $unitShort }}',
        wireMethod: '{{ $safeWireMethod }}',
        targetUserId: {{ $targetUserId !== null ? (int) $targetUserId : 'null' }},
        initialMinutes: {{ (int) ($resolvedCurrent ?? 15) }},
        initialCustom: '{{ $isCustomCurrent ? (int) $resolvedCurrent : '' }}',
        livewire: null,
        init() {
            this.livewire = this.$wire;
        },
        toggle() {
            if (this.open) {
                this.close();
                return;
            }

            this.minutes = this.initialMinutes;
            this.custom = this.initialCustom;
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

            if (window.matchMedia('(max-width: 639px)').matches) {
                this.menuStyle = 'position:fixed;left:0;right:0;bottom:0;top:auto;width:100%;max-height:min(75vh,32rem);overflow-y:auto;border-radius:1rem 1rem 0 0;z-index:10000;';
                return;
            }

            const rect = trigger.getBoundingClientRect();
            const gap = 2;
            const panelWidth = Math.min(320, window.innerWidth - (gap * 2));
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
                this.menuStyle = `position:fixed;left:${left}px;bottom:${window.innerHeight - rect.top + gap}px;top:auto;width:${panelWidth}px;max-height:${Math.max(spaceAbove, 200)}px;overflow-y:auto;z-index:10000;`;
            } else {
                this.menuStyle = `position:fixed;left:${left}px;top:${rect.bottom + gap}px;bottom:auto;width:${panelWidth}px;max-height:${Math.max(spaceBelow, 200)}px;overflow-y:auto;z-index:10000;`;
            }
        },
        onWindowPointerDown(event) {
            if (! this.open) {
                return;
            }
            if (this.$refs.trigger?.contains(event.target) || this.$refs.panel?.contains(event.target)) {
                return;
            }
            this.close();
        },
        callWire(...args) {
            if (! this.livewire || typeof this.livewire[this.wireMethod] !== 'function') {
                return;
            }
            this.livewire[this.wireMethod](...args);
        },
        selectPreset(mins) {
            this.minutes = mins;
            this.custom = '';
            this.submit(mins);
        },
        applyCustom() {
            const value = Number.parseInt(String(this.custom ?? '').replace(/[^\d]/g, ''), 10);
            if (! Number.isFinite(value) || value < 1 || value > 240) {
                return;
            }
            this.minutes = value;
            this.submit(value);
        },
        submit(value) {
            if (! Number.isFinite(value) || value < 1 || value > 240) {
                return;
            }
            if (this.targetUserId !== null) {
                this.callWire(this.targetUserId, value);
            } else {
                this.callWire(value);
            }
            this.close();
        },
        clearLate() {
            if (this.targetUserId !== null) {
                this.callWire(this.targetUserId, null);
            } else {
                this.callWire(null);
            }
            this.close();
        },
    }"
    x-on:ui-overflow-menu-open.window="if ($event.detail.el !== $el) close()"
    x-on:keydown.escape.window="close()"
    x-on:pointerdown.window="onWindowPointerDown($event)"
    x-on:resize.window="open && updatePosition()"
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

    <div
        x-ref="panel"
        x-show="open"
        x-cloak
        role="dialog"
        aria-modal="true"
        aria-label="{{ $label }}"
        class="ui-late-announce-panel flex flex-col gap-4 border border-base-300 bg-base-100 p-4 shadow-lg light:border-neutral max-sm:pb-[max(1rem,env(safe-area-inset-bottom))] sm:rounded-box"
        :style="menuStyle"
        x-on:pointerdown.stop
        @if (is_string($triggerDataUi) && $triggerDataUi !== '')
            data-ui="{{ $triggerDataUi }}-panel"
        @endif
    >
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <p class="text-sm font-semibold text-base-content">{{ $label }}</p>
                <p class="mt-0.5 text-xs text-base-content/60">{{ __('ui.activities.late_minutes_hint') }}</p>
            </div>
            <button
                type="button"
                class="btn btn-ghost btn-square btn-sm shrink-0"
                x-on:click.stop="close()"
                aria-label="{{ __('ui.common.close') }}"
            >
                <x-icon name="o-x-mark" class="h-5 w-5" />
            </button>
        </div>

        <div class="grid grid-cols-3 gap-2" role="group" aria-label="{{ __('ui.activities.late_minutes_presets') }}">
            @foreach ($presets as $preset)
                <button
                    type="button"
                    class="btn btn-md h-12 min-h-12 touch-manipulation"
                    x-bind:class="minutes === {{ $preset }} && custom === '' ? 'btn-warning' : 'btn-outline'"
                    x-on:click.stop="selectPreset({{ $preset }})"
                    data-ui="late-announce-preset"
                >{{ $preset }}{{ $unitShort }}</button>
            @endforeach
        </div>

        <div class="space-y-2">
            <label class="text-xs font-medium text-base-content/70" for="{{ $inputId }}">
                {{ __('ui.activities.late_minutes_custom') }}
            </label>
            <div class="flex gap-2">
                <input
                    id="{{ $inputId }}"
                    type="text"
                    inputmode="numeric"
                    enterkeyhint="done"
                    autocomplete="off"
                    maxlength="3"
                    class="input input-bordered h-12 min-h-12 w-full touch-manipulation text-base"
                    placeholder="{{ __('ui.activities.late_minutes_placeholder') }}"
                    x-model="custom"
                    x-on:keydown.enter.prevent="applyCustom()"
                    data-ui="late-announce-minutes-input"
                />
                <button
                    type="button"
                    class="btn btn-primary h-12 min-h-12 shrink-0 touch-manipulation px-4"
                    x-on:click.stop="applyCustom()"
                    data-ui="late-announce-confirm"
                >
                    {{ __('ui.common.confirm') }}
                </button>
            </div>
        </div>

        @if ($hasLate)
            <button
                type="button"
                class="btn btn-ghost btn-block h-11 touch-manipulation"
                x-on:click.stop="clearLate()"
                data-ui="late-announce-clear"
            >
                {{ __('ui.activities.late_clear') }}
            </button>
        @endif
    </div>
</div>
