@if ($items !== [])
    <div
        {{ $attributes->class(['my-2', 'flex', 'flex-wrap', 'gap-x-2', 'gap-y-3']) }}
        @if (filled($dataUi)) data-ui="{{ $dataUi }}" @endif
    >
        @foreach ($items as $item)
            <span
                @class([
                    $item->semantic->badgeClasses($item->outline),
                    'whitespace-normal text-left' => $item->normalWrap,
                ])
                @if (filled($item->dataUi)) data-ui="{{ $item->dataUi }}" @endif
                @if (filled($item->title)) title="{{ $item->title }}" @endif
            >{{ $item->label }}</span>
        @endforeach
    </div>
@endif
