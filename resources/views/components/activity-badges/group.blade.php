@if ($items !== [])
    <div
        {{ $attributes->class(['my-2', 'flex', 'flex-wrap', 'gap-x-2', 'gap-y-1']) }}
        @if (filled($dataUi)) data-ui="{{ $dataUi }}" @endif
    >
        @foreach ($items as $item)
            @php
                $hasTooltip = $item->kind === App\Domain\ActivityBadges\ActivityBadgeKind::TaxonomyTag && filled($item->title);
            @endphp
            <x-badge
                :icon="$item->icon"
                :data-tip="$hasTooltip ? $item->title : null"
                @class([
                    $item->semantic->badgeClasses($item->outline),
                    'whitespace-normal text-left' => $item->normalWrap,
                    'gap-1' => filled($item->icon),
                    'tooltip tooltip-primary ui-activity-badge-tooltip' => $hasTooltip,
                ])
                :data-ui="$item->dataUi"
            >
                {{ $item->label }}
            </x-badge>
        @endforeach
    </div>
@endif
