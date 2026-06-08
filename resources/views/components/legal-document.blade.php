@props([
    'document',
])

@php
    $content = legal_replace(trans("legal.{$document}"));
@endphp

<article class="mx-auto max-w-3xl px-4 py-8 sm:py-12">
    <div class="rich-text-content">
        <h1>{{ $content['title'] }}</h1>

        <p class="text-sm opacity-70">{{ legal_replace(__('legal.last_updated')) }}</p>

        @foreach ($content['sections'] as $section)
            <h2>{{ $section['heading'] }}</h2>

            @foreach ($section['paragraphs'] ?? [] as $paragraph)
                <p>{{ $paragraph }}</p>
            @endforeach

            @if (! empty($section['list']))
                <ul>
                    @foreach ($section['list'] as $item)
                        <li>{{ $item }}</li>
                    @endforeach
                </ul>
            @endif
        @endforeach
    </div>
</article>
