@if ($items !== [])
    <div
        {{ $attributes->class(['my-2', 'flex', 'flex-wrap', 'gap-x-2', 'gap-y-3']) }}
        @if (filled($dataUi)) data-ui="{{ $dataUi }}" @endif
    >
        @foreach ($items as $item)
            <x-badge
                value="{{ $item->label }}"
                icon="{{$item->icon}}"
                @class([
                    $item->semantic->badgeClasses($item->outline),
                    'whitespace-normal text-left' => $item->normalWrap,
                    'gap-1' => filled($item->icon),
                ])
                data-ui="{{ $item->dataUi }}"
                title="{{ $item->title }}"
            />
        @endforeach
    </div>
@endif
