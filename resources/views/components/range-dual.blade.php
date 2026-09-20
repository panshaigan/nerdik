@props([
    'label',
    'minWireModel',
    'maxWireModel',
    'minLimit' => 1,
    'maxLimit' => 20,
    'step' => 1,
    'rangeClass' => 'range-xs',
])

@php
    $minLimit = (int) $minLimit;
    $maxLimit = (int) $maxLimit;
    $step = (int) $step;
    $sliderMin = $minLimit - 1;
    $sliderMax = $maxLimit + 1;
@endphp

@pushOnce('head')
    <style>
        /* Re-enable pointer events on thumbs only.
           Dual sliders: DaisyUI paints fill from track start -> thumb via --range-fill + thumb shadows.
           We draw the segment in Blade, so disable native track/fill while keeping thumbs. */
        input[type="range"].range-dual-thumb-only {
            pointer-events: none;
            --range-fill: 0;
            --range-bg: transparent;
        }
        input[type="range"].range-dual-thumb-only::-webkit-slider-runnable-track {
            background-color: transparent;
        }
        input[type="range"].range-dual-thumb-only::-moz-range-track {
            background-color: transparent;
        }
        input[type="range"].range-dual-thumb-only::-webkit-slider-thumb {
            pointer-events: all;
        }
        input[type="range"].range-dual-thumb-only::-moz-range-thumb {
            pointer-events: all;
        }

        .range-dual {
            --range-thumb-size: calc(var(--size-selector, 0.25rem) * 4);
            position: relative;
            width: 100%;
            min-height: var(--range-thumb-size);
        }

        .range-dual-track {
            pointer-events: none;
            position: absolute;
            top: 50%;
            left: 0;
            z-index: 0;
            width: 100%;
            height: calc(var(--range-thumb-size) * 0.5);
            transform: translateY(-50%);
            border-radius: var(--radius-selector, 0.25rem);
            background: color-mix(in oklab, currentColor 10%, transparent);
        }

        .range-dual-fill {
            pointer-events: none;
            position: absolute;
            top: 50%;
            z-index: 1;
            --travel: calc(100% - var(--range-thumb-size));
            --fill-overshoot: calc(var(--range-thumb-size) / 2);
            left: calc(var(--min-p) / 100 * var(--travel) + var(--range-thumb-size) / 2 - var(--fill-overshoot));
            width: calc((var(--max-p) - var(--min-p)) / 100 * var(--travel) + var(--range-thumb-size));
            height: var(--range-thumb-size);
            transform: translateY(-50%);
            border-radius: var(--brand-radius-field);
            background-color: var(--brand-base-content-dark);
        }
        [data-theme="light"] .range-dual-fill {
            background-color: var(--brand-base-content-light);
        }
    </style>
@endPushOnce

<div
    x-data="{
        min: @entangle($minWireModel),
        max: @entangle($maxWireModel),
        minLimit: {{ $minLimit }},
        maxLimit: {{ $maxLimit }},
        sliderMin: {{ $sliderMin }},
        sliderMax: {{ $sliderMax }},
        step: {{ $step }},
        sliderMinValue: {{ $sliderMin }},
        sliderMaxValue: {{ $sliderMax }},
        rangeOpenMax: @js(__('ui.activities.range_open_max')),
        rangeOpenMin: @js(__('ui.activities.range_open_min')),
        rangeBounded: @js(__('ui.activities.range_bounded')),
        syncingFromWire: false,

        clamp(value, lo, hi) {
            return Math.min(hi, Math.max(lo, value));
        },

        boundIsOpen(value) {
            return value === null || value === '';
        },

        sliderFromMin() {
            return this.boundIsOpen(this.min) ? this.sliderMin : Number(this.min);
        },

        sliderFromMax() {
            return this.boundIsOpen(this.max) ? this.sliderMax : Number(this.max);
        },

        percentForSlider(value) {
            const lo = this.sliderMin;
            const hi = this.sliderMax;
            if (hi === lo) {
                return 0;
            }

            return this.clamp(((value - lo) / (hi - lo)) * 100, 0, 100);
        },

        get minPercent() {
            return this.percentForSlider(this.sliderMinValue);
        },
        get maxPercent() {
            return this.percentForSlider(this.sliderMaxValue);
        },

        get formattedRange() {
            const minOpen = this.boundIsOpen(this.min);
            const maxOpen = this.boundIsOpen(this.max);
            if (minOpen && maxOpen) {
                return '';
            }
            if (! minOpen && maxOpen) {
                return this.rangeOpenMax.replace(':min', String(this.min));
            }
            if (minOpen && ! maxOpen) {
                return this.rangeOpenMin.replace(':max', String(this.max));
            }

            return this.rangeBounded
                .replace(':min', String(this.min))
                .replace(':max', String(this.max));
        },

        applySliderToWire() {
            this.syncingFromWire = true;
            this.min = this.sliderMinValue === this.sliderMin ? null : this.sliderMinValue;
            this.max = this.sliderMaxValue === this.sliderMax ? null : this.sliderMaxValue;
            this.$nextTick(() => {
                this.syncingFromWire = false;
            });
        },

        onMinSliderInput() {
            if (this.sliderMinValue > this.sliderMaxValue) {
                this.sliderMinValue = this.sliderMaxValue;
            }
            if (this.sliderMinValue === this.sliderMax) {
                this.sliderMinValue = this.maxLimit;
            }
            this.applySliderToWire();
        },

        onMaxSliderInput() {
            if (this.sliderMaxValue < this.sliderMinValue) {
                this.sliderMaxValue = this.sliderMinValue;
            }
            if (this.sliderMaxValue === this.sliderMin) {
                this.sliderMaxValue = this.minLimit;
            }
            this.applySliderToWire();
        },

        init() {
            this.sliderMinValue = this.sliderFromMin();
            this.sliderMaxValue = this.sliderFromMax();

            this.$watch('min', () => {
                if (this.syncingFromWire) {
                    return;
                }
                this.sliderMinValue = this.sliderFromMin();
            });

            this.$watch('max', () => {
                if (this.syncingFromWire) {
                    return;
                }
                this.sliderMaxValue = this.sliderFromMax();
            });
        },
    }"
    {{ $attributes->class(['space-y-1']) }}
>
    <label class="text-sm font-medium flex justify-between">
        <span>{{ $label }}</span>
        <span class="font-semibold" x-text="formattedRange"></span>
    </label>

    <div class="range-dual text-base-content">
        <div class="range-dual-track" aria-hidden="true"></div>
        <div
            class="range-dual-fill"
            aria-hidden="true"
            :style="{ '--min-p': minPercent, '--max-p': maxPercent }"
        ></div>

        <input
            type="range"
            x-model.number="sliderMinValue"
            @input="onMinSliderInput()"
            :min="sliderMin"
            :max="sliderMax"
            :step="step"
            class="range-dual-thumb-only range absolute top-1/2 left-0 z-20 w-full -translate-y-1/2 {{ $rangeClass }}"
            :class="sliderMinValue > ((sliderMin + sliderMax) / 2) ? 'z-30' : 'z-20'"
        >
        <input
            type="range"
            x-model.number="sliderMaxValue"
            @input="onMaxSliderInput()"
            :min="sliderMin"
            :max="sliderMax"
            :step="step"
            class="range-dual-thumb-only range absolute top-1/2 left-0 z-10 w-full -translate-y-1/2 {{ $rangeClass }}"
            :class="sliderMaxValue <= ((sliderMin + sliderMax) / 2) ? 'z-30' : 'z-10'"
        >
    </div>
</div>
