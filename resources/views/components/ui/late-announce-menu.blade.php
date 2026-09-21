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
    $unitShort = __('ui.activities.late_minutes_unit_short');
    $safeWireMethod = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $wireMethod) ?: 'announceLate';
@endphp

<div
    {{ $attributes->except('data-ui')->class('relative inline-flex') }}
    x-data="{
        open: false,
        openUpward: {{ $openUpward ? 'true' : 'false' }},
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
            this.menuStyle = '';
        },
        updatePosition() {
            const trigger = this.$refs.trigger;
            const panel = this.$refs.panel;
            if (! trigger || ! panel || ! this.open) {
                return;
            }

            if (window.matchMedia('(max-width: 639px)').matches) {
                this.menuStyle = 'left:0;right:0;bottom:0;top:auto;width:100%;max-height:min(70vh,24rem);border-radius:1rem 1rem 0 0;';
                return;
            }

            const rect = trigger.getBoundingClientRect();
            const gap = 2;
            const panelWidth = Math.min(20 * 16, window.innerWidth - (gap * 2));
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
                this.menuStyle = `left:${left}px;bottom:${window.innerHeight - rect.top + gap}px;top:auto;width:${panelWidth}px;max-height:${Math.max(spaceAbove, 160)}px;`;
            } else {
                this.menuStyle = `left:${left}px;top:${rect.bottom + gap}px;bottom:auto;width:${panelWidth}px;max-height:${Math.max(spaceBelow, 160)}px;`;
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
        choose(mins) {
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
        x-cloak
        x-bind:class="open ? 'flex' : 'hidden'"
        role="dialog"
        aria-modal="true"
        aria-label="{{ $label }}"
        class="ui-late-announce-panel fixed z-[10000] max-h-[min(70vh,24rem)] flex-col gap-3 overflow-y-auto border border-base-300 bg-base-100 p-4 shadow-lg light:border-neutral max-sm:pb-[max(1rem,env(safe-area-inset-bottom))] sm:w-80 sm:rounded-box"
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
                @if ($targetUserId !== null)
                    <button
                        type="button"
                        class="btn btn-outline btn-md h-12 min-h-12 touch-manipulation {{ $resolvedCurrent === $preset ? 'btn-warning' : '' }}"
                        wire:click="{{ $safeWireMethod }}({{ (int) $targetUserId }}, {{ $preset }})"
                        x-on:click="choose({{ $preset }})"
                        data-ui="late-announce-preset"
                    >{{ $preset }}{{ $unitShort }}</button>
                @else
                    <button
                        type="button"
                        class="btn btn-outline btn-md h-12 min-h-12 touch-manipulation {{ $resolvedCurrent === $preset ? 'btn-warning' : '' }}"
                        wire:click="{{ $safeWireMethod }}({{ $preset }})"
                        x-on:click="choose({{ $preset }})"
                        data-ui="late-announce-preset"
                    >{{ $preset }}{{ $unitShort }}</button>
                @endif
            @endforeach
        </div>

        @if ($hasLate)
            @if ($targetUserId !== null)
                <button
                    type="button"
                    class="btn btn-ghost btn-block h-11 touch-manipulation"
                    wire:click="{{ $safeWireMethod }}({{ (int) $targetUserId }}, null)"
                    x-on:click="choose(null)"
                    data-ui="late-announce-clear"
                >
                    {{ __('ui.activities.late_clear') }}
                </button>
            @else
                <button
                    type="button"
                    class="btn btn-ghost btn-block h-11 touch-manipulation"
                    wire:click="{{ $safeWireMethod }}(null)"
                    x-on:click="choose(null)"
                    data-ui="late-announce-clear"
                >
                    {{ __('ui.activities.late_clear') }}
                </button>
            @endif
        @endif
    </div>
</div>
