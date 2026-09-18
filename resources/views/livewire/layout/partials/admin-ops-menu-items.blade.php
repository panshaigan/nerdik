@php
    /** @var bool $closeOnClick */
    $closeOnClick = $closeOnClick ?? false;
@endphp

@foreach (\App\Support\AdminOpsNavLinks::items() as $index => $link)
    <li @class(['mt-1 border-t border-base-300 pt-1 light:border-neutral' => $index === 0])>
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
