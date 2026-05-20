@if ($items !== [])
    <div
        {{ $attributes->class(['my-2', 'flex', 'flex-wrap', 'gap-x-2', 'gap-y-1']) }}
        @if (filled($dataUi)) data-ui="{{ $dataUi }}" @endif
    >
        @foreach ($items as $item)
            <div
                @class([
                    'tooltip tooltip-primary' => $item->kind === App\Domain\ActivityBadges\ActivityBadgeKind::TaxonomyTag && filled($item->title),
                ])
                @if ($item->kind === App\Domain\ActivityBadges\ActivityBadgeKind::TaxonomyTag && filled($item->title))
                    data-tip="{{ $item->title }}"
                @endif
            >
                <x-badge
                    :icon="$item->icon"
                    @class([
                        $item->semantic->badgeClasses($item->outline),
                        'whitespace-normal text-left' => $item->normalWrap,
                        'gap-1' => filled($item->icon),
                    ])
                    :data-ui="$item->dataUi"
                >
                    {{ $item->label }}
                </x-badge>
            </div>
        @endforeach
    </div>
@endif
