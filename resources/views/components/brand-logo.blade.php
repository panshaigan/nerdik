<svg
    viewBox="0 0 100 100"
    xmlns="http://www.w3.org/2000/svg"
    fill="none"
    stroke="currentColor"
    stroke-width="1.5"
    stroke-linecap="round"
    stroke-linejoin="round"
    aria-hidden="true"
    {{ $attributes }}
>
    {{-- Outer triangle --}}
    <polygon points="50,8 10,92 90,92" />

    {{-- Faceted diamond --}}
    <polygon points="50,22 62,38 50,52 38,38" />
    <line x1="50" y1="22" x2="50" y2="52" />
    <line x1="38" y1="38" x2="62" y2="38" />

    {{-- Chalice bowl --}}
    <path d="M 32 58 Q 50 48 68 58" />
    <path d="M 32 58 Q 42 64 50 66" />
    <path d="M 68 58 Q 58 64 50 66" />

    {{-- Chalice stem and base --}}
    <line x1="50" y1="66" x2="50" y2="74" />
    <path d="M 50 74 Q 40 80 34 86 Q 50 92 50 92" />
    <path d="M 50 74 Q 60 80 66 86 Q 50 92 50 92" />

    {{-- Chalice bowl center dot --}}
    <circle cx="50" cy="56" r="1.75" fill="currentColor" stroke="none" />
</svg>
