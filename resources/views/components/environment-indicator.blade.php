@php
    $indicator = \App\Support\EnvironmentIndicator::definition();
@endphp

@if ($indicator !== null)
    <div
        data-ui="environment-indicator"
        aria-hidden="true"
        style="position: fixed; top: 0; left: 0; z-index: 9999; width: 88px; height: 88px; overflow: hidden; pointer-events: none;"
    >
        <div
            style="
                position: absolute;
                top: 18px;
                left: -28px;
                width: 140px;
                padding: 4px 0;
                background-color: {{ $indicator['color'] }};
                color: #ffffff;
                font-family: ui-sans-serif, system-ui, sans-serif;
                font-size: 11px;
                font-weight: 700;
                letter-spacing: 0.08em;
                line-height: 1;
                text-align: center;
                text-transform: uppercase;
                transform: rotate(-45deg);
                box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2);
            "
        >
            {{ $indicator['label'] }}
        </div>
    </div>
@endif
