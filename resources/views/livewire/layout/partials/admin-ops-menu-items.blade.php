@php
    /** @var bool $closeOnClick */
    $closeOnClick = $closeOnClick ?? false;
@endphp

@foreach (\App\Support\AdminOpsNavLinks::groups() as $groupIndex => $group)
    @if ($group['label'] !== null)
        <li @class([
            'menu-title',
            'pt-2' => $groupIndex > 0,
        ])>
            <span class="text-xs font-semibold uppercase tracking-wide text-base-content/50">
                {{ $group['label'] }}
            </span>
        </li>
    @endif

    @foreach ($group['items'] as $link)
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
@endforeach
