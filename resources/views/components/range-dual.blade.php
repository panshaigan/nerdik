@props([
    'label',
    'minWireModel',
    'maxWireModel',
    'minLimit' => 1,
    'maxLimit' => 20,
    'step' => 1,
    'rangeClass' => 'range-xs',
])

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
            left: 0;
            z-index: 1;
            height: var(--range-thumb-size);
            transform: translateY(-50%);
            border-radius: calc(var(--range-thumb-size) / 3);
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
        minLimit: {{ (int) $minLimit }},
        maxLimit: {{ (int) $maxLimit }},
        step: {{ (int) $step }},

        get minPercent() {
            const val = Number(this.min ?? this.minLimit);
            const lo = this.minLimit;
            const hi = this.maxLimit;
            if (!Number.isFinite(val)) {
                return 0;
            }

            return ((val - lo) / (hi - lo)) * 100;
        },
        get maxPercent() {
            const val = Number(this.max ?? this.maxLimit);
            const lo = this.minLimit;
            const hi = this.maxLimit;
            if (!Number.isFinite(val)) {
                return 100;
            }

            return ((val - lo) / (hi - lo)) * 100;
        },

        init() {
            this.min = this.min ?? this.minLimit;
            this.max = this.max ?? this.maxLimit;

            this.$watch('min', (value) => {
                const minValue = Number(value);
                const maxValue = Number(this.max);
                if (minValue > maxValue) {
                    this.min = maxValue;
                }
            });

            this.$watch('max', (value) => {
                const maxValue = Number(value);
                const minValue = Number(this.min);
                if (maxValue < minValue) {
                    this.max = minValue;
                }
            });
        },
    }"
    {{ $attributes->class(['space-y-1']) }}
>
    <label class="text-sm font-medium flex justify-between">
        <span>{{ $label }}</span>
        <span class="font-semibold" x-text="`${min}–${max}`"></span>
    </label>

    <div class="range-dual text-base-content">
        <div class="range-dual-track" aria-hidden="true"></div>
        <div
            class="range-dual-fill"
            aria-hidden="true"
            :style="{ left: minPercent + '%', width: Math.max(0, maxPercent - minPercent + 2) + '%' }"
        ></div>

        <input
            type="range"
            x-model.number="min"
            :min="minLimit"
            :max="maxLimit"
            :step="step"
            class="range-dual-thumb-only range absolute top-1/2 left-0 z-20 w-full -translate-y-1/2 {{ $rangeClass }}"
            :class="min > (maxLimit / 2) ? 'z-30' : 'z-20'"
        >
        <input
            type="range"
            x-model.number="max"
            :min="minLimit"
            :max="maxLimit"
            :step="step"
            class="range-dual-thumb-only range absolute top-1/2 left-0 z-10 w-full -translate-y-1/2 {{ $rangeClass }}"
            :class="max <= (maxLimit / 2) ? 'z-30' : 'z-10'"
        >
    </div>
</div>
