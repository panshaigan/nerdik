@php
    $light = config('branding.light', []);
    $dark = config('branding.dark', []);
@endphp
{{-- Overrides DaisyUI theme tokens; load after Vite bundle. --}}
<style id="app-branding-colors">
    :root,
    [data-theme="light"] {
        @foreach ($light as $name => $value)
            --color-{{ $name }}: {{ $value }};
        @endforeach
    }
    [data-theme="dark"] {
        @foreach ($dark as $name => $value)
            --color-{{ $name }}: {{ $value }};
        @endforeach
    }
</style>
