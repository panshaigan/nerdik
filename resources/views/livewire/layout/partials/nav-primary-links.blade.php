@php
    use App\Support\Browse\BrowseSearchState;

    /** @var 'bar'|'drawer' $variant */
    $variant = $variant ?? 'bar';
    $closeOnClick = $closeOnClick ?? false;

    $barLinkClass = function (bool $active): string {
        $base = 'ui-nav-link font-display inline-flex items-center border-b-2 bg-transparent px-1 pt-1 text-sm font-medium transition hover:bg-transparent focus:bg-transparent';

        return $active
            ? $base.' is-active border-primary text-base-content'
            : $base.' border-transparent text-base-content/70 hover:border-base-300 hover:text-base-content';
    };

    $drawerLinkClass = fn (bool $active): string => $active ? 'active font-display font-medium' : 'font-display';

    $links = [
        [
            'href' => BrowseSearchState::indexUrl(),
            'active' => request()->routeIs('search.index'),
            'icon' => 'o-magnifying-glass',
            'label' => __('ui.nav.search'),
        ],
        [
            'href' => route('catalog.places'),
            'active' => request()->routeIs('catalog.places'),
            'icon' => 'o-map-pin',
            'label' => __('ui.nav.places'),
        ],
        [
            'href' => route('catalog.organizations'),
            'active' => request()->routeIs('catalog.organizations'),
            'icon' => 'o-building-office-2',
            'label' => __('ui.nav.organizations'),
        ],
        [
            'href' => route('catalog.series'),
            'active' => request()->routeIs('catalog.series'),
            'icon' => 'o-rectangle-stack',
            'label' => __('ui.nav.series'),
        ],
    ];
@endphp

@if ($variant === 'bar')
    @foreach ($links as $link)
        <a
            href="{{ $link['href'] }}"
            wire:navigate
            class="{{ $barLinkClass($link['active']) }} gap-1.5"
        >
            <x-icon :name="$link['icon']" class="h-4 w-4 shrink-0" />
            {{ $link['label'] }}
        </a>
    @endforeach
@else
    @foreach ($links as $link)
        <li>
            <a
                href="{{ $link['href'] }}"
                wire:navigate
                @if ($closeOnClick)
                    @click="close()"
                @endif
                class="{{ $drawerLinkClass($link['active']) }}"
            >
                <x-icon :name="$link['icon']" class="h-4 w-4 shrink-0" />
                {{ $link['label'] }}
            </a>
        </li>
    @endforeach
@endif
