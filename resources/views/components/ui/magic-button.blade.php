@props([
    'link' => null,
    'size' => 'btn-md',
    'variant' => 'btn-primary',
])

@once
    <style>
        .ui-magic-button {
            --magic-p: var(--brand-primary);
            --magic-s: var(--brand-secondary);
            --magic-a: var(--brand-accent);

            position: relative;
            isolation: isolate;
            overflow: hidden;
            border: 1px solid color-mix(in oklab, var(--magic-p) 35%, transparent);
            color: var(--brand-primary-content);
            background: linear-gradient(
                90deg,
                color-mix(in oklab, var(--magic-p) 100%, transparent),
                color-mix(in oklab, var(--magic-p) 80%, var(--magic-s))
            );
            box-shadow: 0 0 0 rgba(0 0 0 / 0);
            transition:
                transform 500ms ease-out,
                box-shadow 500ms ease-out,
                border-color 500ms ease-out;
        }

        .ui-magic-button::before {
            content: "";
            position: absolute;
            inset: 0;
            z-index: 0;
            pointer-events: none;
            opacity: 0;
            transition: opacity 500ms ease-out;
            background:
                radial-gradient(circle at 20% 25%, color-mix(in oklab, var(--magic-a) 45%, transparent), transparent 48%),
                radial-gradient(circle at 80% 80%, color-mix(in oklab, var(--magic-s) 45%, transparent), transparent 52%);
        }

        .ui-magic-button > * {
            position: relative;
            z-index: 1;
        }

        @media (hover: hover) {
            .ui-magic-button:hover {
                transform: translateY(-0.25rem) scale(1.03);
                border-color: color-mix(in oklab, var(--magic-p) 55%, transparent);
                box-shadow:
                    0 0 24px color-mix(in oklab, var(--magic-p) 55%, transparent),
                    0 0 64px color-mix(in oklab, var(--magic-s) 35%, transparent);
            }

            .ui-magic-button:hover::before {
                opacity: 1;
            }
        }

        .ui-magic-button:focus-visible {
            outline: 2px solid color-mix(in oklab, var(--magic-p) 70%, transparent);
            outline-offset: 2px;
        }

        @media (prefers-reduced-motion: reduce) {
            .ui-magic-button {
                transition: none;
            }

            .ui-magic-button:hover {
                transform: none;
            }

            .ui-magic-button::before {
                transition: none;
            }
        }
    </style>
@endonce

<x-button
    :link="$link"
    {{ $attributes->class([
        $variant,
        $size,
        'ui-magic-button',
    ]) }}
>
    {{ $slot }}
</x-button>
