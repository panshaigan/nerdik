@if ($items !== [])
    @php
        $shouldCollapse = $shouldCollapse();
        $visibleLimit = $shouldCollapse ? $collapseAfter : count($items);
    @endphp
    <div
        @if ($shouldCollapse) x-data="{ expanded: false }" @endif
        {{ $attributes->class(['my-2', 'flex', 'flex-wrap', 'gap-x-1', 'gap-y-3', 'ui-activity-badge-tags']) }}
        @if (filled($dataUi)) data-ui="{{ $dataUi }}" @endif
    >
        @foreach ($items as $index => $item)
            @php
                $hasTooltip = $item->kind === App\Domain\ActivityBadges\ActivityBadgeKind::TaxonomyTag && filled($item->title);
                $isOverflow = $shouldCollapse && $index >= $visibleLimit;
            @endphp
            @if ($isOverflow)
                <span class="contents" x-show="expanded" x-cloak>
            @endif
            <x-badge
                :icon="$item->icon"
                :data-tip="$hasTooltip ? $item->title : null"
                @class([
                    $item->semantic->badgeClasses($item->outline),
                    'leading-none',
                    'whitespace-normal text-left' => $item->normalWrap,
                    'gap-1' => filled($item->icon),
                    'tooltip tooltip-primary ui-activity-badge-tooltip' => $hasTooltip,
                ])
                :data-ui="$item->dataUi"
            >
                <span class="leading-none">{{ $item->label }}</span>
            </x-badge>
            @if ($isOverflow)
                </span>
            @endif
        @endforeach

        @if ($shouldCollapse)
            <button
                type="button"
                class="btn btn-ghost btn-xs h-auto min-h-0 px-2 py-1 text-xs font-semibold text-primary hover:bg-primary/10"
                x-on:click="expanded = ! expanded"
                data-ui="activity-badge-group-toggle"
            >
                <span x-show="! expanded">{{ __('ui.common.show_more') }}</span>
                <span x-show="expanded" x-cloak>{{ __('ui.common.show_less') }}</span>
            </button>
        @endif
    </div>
@endif
