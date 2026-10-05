@php
    $fontFiles = [
        'resources/fonts/figtree-latin-wght-normal.woff2',
        'resources/fonts/cinzel-latin-wght-normal.woff2',
    ];

    if (str_starts_with(str_replace('_', '-', app()->getLocale()), 'pl')) {
        $fontFiles[] = 'resources/fonts/figtree-latin-ext-wght-normal.woff2';
        $fontFiles[] = 'resources/fonts/cinzel-latin-ext-wght-normal.woff2';
    }
@endphp
@foreach ($fontFiles as $fontFile)
    @php
        try {
            $fontUrl = Vite::asset($fontFile);
        } catch (Throwable) {
            $fontUrl = null;
        }
    @endphp
    @if (filled($fontUrl))
        <link rel="preload" href="{{ $fontUrl }}" as="font" type="font/woff2" crossorigin>
    @endif
@endforeach
