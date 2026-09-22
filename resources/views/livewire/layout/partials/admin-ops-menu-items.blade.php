@php
    /** @var bool $closeOnClick */
    $closeOnClick = $closeOnClick ?? false;
@endphp

@foreach (\App\Support\AdminOpsNavLinks::items() as $link)
    <li>
        <a
            href="{{ $link['url'] }}"
            target="_blank"
            rel="noopener noreferrer"
            @if ($closeOnClick)
                @click="close()"
            @endif
        >{{ $link['label'] }}</a>
    </li>
@endforeach
