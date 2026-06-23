@php
    $localeLink = fn (bool $active): string => $active
        ? 'ui-nav-locale is-active font-display border-b-2 border-primary text-base-content'
        : 'ui-nav-locale font-display border-b-2 border-transparent text-base-content/70 hover:text-base-content';
@endphp

<nav
    x-data="{
        localeSwitchUrl(base) {
            return base + '?redirect=' + encodeURIComponent(
                window.location.pathname + window.location.search + window.location.hash,
            );
        },
    }"
    class="ui-app-navigation -mx-3 flex flex-1 items-center justify-end gap-2"
>
    <a
        wire:navigate
        x-bind:href="localeSwitchUrl('{{ route('locale.switch', ['locale' => 'en']) }}')"
        class="btn btn-ghost btn-sm {{ $localeLink(app()->getLocale() === 'en') }}"
    >
        {{ __('ui.common.language_en') }}
    </a>
    <a
        wire:navigate
        x-bind:href="localeSwitchUrl('{{ route('locale.switch', ['locale' => 'pl']) }}')"
        class="btn btn-ghost btn-sm {{ $localeLink(app()->getLocale() === 'pl') }}"
    >
        {{ __('ui.common.language_pl') }}
    </a>

    @auth
        <x-button :link="url('/dashboard')" class="btn-primary btn-sm">
            {{ __('ui.nav.dashboard') }}
        </x-button>
    @else
        <x-button :link="route('login')" class="btn-ghost btn-sm">
            {{ __('ui.nav.log_in') }}
        </x-button>

        @if (Route::has('register'))
            <x-button :link="route('register')" class="btn-primary btn-sm">
                {{ __('ui.nav.register') }}
            </x-button>
        @endif
    @endauth
</nav>
