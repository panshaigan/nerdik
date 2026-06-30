@php
    $localeLink = fn (bool $active): string => $active
        ? 'ui-nav-locale is-active font-display border-b-2 border-primary text-base-content'
        : 'ui-nav-locale font-display border-b-2 border-transparent text-base-content/70 hover:text-base-content';
@endphp

<nav
    x-data="{
        open: false,
        toggle() {
            this.open = ! this.open;
            this.syncBody();
        },
        close() {
            this.open = false;
            this.syncBody();
            this.$nextTick(() => this.$refs.menuToggle?.focus());
        },
        syncBody() {
            document.body.style.overflow = this.open ? 'hidden' : '';
        },
        localeSwitchUrl(base) {
            return base + '?redirect=' + encodeURIComponent(
                window.location.pathname + window.location.search + window.location.hash,
            );
        },
    }"
    @keydown.escape.window="open && close()"
    class="ui-app-navigation -mx-3 flex flex-1 items-center justify-end"
>
    <div class="hidden items-center gap-2 sm:flex">
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
    </div>

    <div class="flex items-center sm:hidden">
        <x-button
            type="button"
            x-ref="menuToggle"
            @click="toggle()"
            x-bind:aria-expanded="open"
            aria-controls="mobile-welcome-nav-drawer"
            aria-label="{{ __('ui.nav.open_menu') }}"
            class="btn-ghost btn-square rounded-md opacity-70 transition duration-150 ease-in-out hover:bg-base-200 hover:opacity-100 focus:outline-none"
        >
            <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </x-button>
    </div>

    <template x-teleport="body">
        <div
            x-show="open"
            x-cloak
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 z-50 sm:hidden"
        >
            <button
                type="button"
                @click="close()"
                aria-label="{{ __('Close menu') }}"
                class="absolute inset-0 bg-base-content/20 backdrop-blur-sm"
            ></button>

            <aside
                id="mobile-welcome-nav-drawer"
                role="dialog"
                aria-modal="true"
                aria-label="{{ __('ui.nav.main_navigation') }}"
                x-show="open"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="translate-x-full"
                x-transition:enter-end="translate-x-0"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="translate-x-0"
                x-transition:leave-end="translate-x-full"
                class="absolute inset-y-0 end-0 flex w-[min(20rem,calc(100vw-3rem))] flex-col border-s border-base-300 bg-base-100 shadow-2xl"
            >
                <div class="flex-1 overflow-y-auto">
                    <div class="border-b border-base-300 px-4 py-4">
                        <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-base-content/50">
                            {{ __('ui.nav.preferences') }}
                        </p>
                        <div class="join">
                            <a
                                wire:navigate
                                x-bind:href="localeSwitchUrl('{{ route('locale.switch', ['locale' => 'en']) }}')"
                                @click="close()"
                                class="join-item btn btn-sm font-display {{ app()->getLocale() === 'en' ? 'border-b-2 border-primary bg-transparent text-base-content shadow-none ui-nav-locale is-active' : 'btn-ghost ui-nav-locale' }}"
                            >
                                {{ __('ui.common.language_en') }}
                            </a>
                            <a
                                wire:navigate
                                x-bind:href="localeSwitchUrl('{{ route('locale.switch', ['locale' => 'pl']) }}')"
                                @click="close()"
                                class="join-item btn btn-sm font-display {{ app()->getLocale() === 'pl' ? 'border-b-2 border-primary bg-transparent text-base-content shadow-none ui-nav-locale is-active' : 'btn-ghost ui-nav-locale' }}"
                            >
                                {{ __('ui.common.language_pl') }}
                            </a>
                        </div>
                    </div>

                    <div class="px-4 py-4">
                        <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-base-content/50">
                            {{ __('ui.nav.account') }}
                        </p>
                        <ul class="menu menu-lg w-full px-0">
                            @auth
                                <li>
                                    <a href="{{ url('/dashboard') }}" wire:navigate @click="close()" class="font-display">
                                        {{ __('ui.nav.dashboard') }}
                                    </a>
                                </li>
                            @else
                                <li>
                                    <a href="{{ route('login') }}" wire:navigate @click="close()" class="font-display">
                                        {{ __('ui.nav.log_in') }}
                                    </a>
                                </li>
                                @if (Route::has('register'))
                                    <li>
                                        <a href="{{ route('register') }}" wire:navigate @click="close()" class="font-display">
                                            {{ __('ui.nav.register') }}
                                        </a>
                                    </li>
                                @endif
                            @endauth
                        </ul>
                    </div>
                </div>
            </aside>
        </div>
    </template>
</nav>
