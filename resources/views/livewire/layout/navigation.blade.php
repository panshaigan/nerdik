<?php

use App\Livewire\Actions\Logout;
use App\Support\Browse\BrowseSearchState;
use App\Support\Browse\BrowseSearchUrl;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

new class extends Component
{
    public ?string $navAvatarUrl = null;

    #[On('database-notifications-updated')]
    public function refreshNotificationIndicators(): void
    {
        //
    }

    #[On('user-requests-updated')]
    public function refreshRequestIndicators(): void
    {
        //
    }

    #[On('profile-avatar-updated')]
    public function refreshNavigationAvatar(?string $avatarUrl = null): void
    {
        $this->navAvatarUrl = is_string($avatarUrl) && $avatarUrl !== '' ? $avatarUrl : null;

        $user = Auth::user();

        if ($user !== null) {
            $user->unsetRelation('media');
            $user->load('media');
        }
    }

    /**
     * Log the current user out of the application.
     */
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: true);
    }

    public function openOrganizerRequestModal(): void
    {
        $this->dispatch('open-send-user-request', type: 'event_organizer_flag');
    }
}; ?>

@php
    $navLink = function (bool $active): string {
        $base = 'ui-nav-link font-display inline-flex items-center border-b-2 bg-transparent px-1 pt-1 text-sm font-medium transition hover:bg-transparent focus:bg-transparent';

        return $active
            ? $base.' is-active border-primary text-base-content'
            : $base.' border-transparent text-base-content/70 hover:border-base-300 hover:text-base-content';
    };

    $mobileNavLink = fn (bool $active): string => $active ? 'active font-display font-medium' : 'font-display';

    $brandUrl = auth()->check() ? route('dashboard') : url('/');
@endphp

<div
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
    class="ui-app-navigation"
>
    @php
        $navSurface = match (true) {
            app()->environment('local') => 'bg-success/35 backdrop-blur-md',
            //app()->environment('staging') => 'bg-warning/35 backdrop-blur-md',
            default => 'bg-black/35 backdrop-blur-md',
        };
    @endphp
    <x-nav
        full-width
        role="navigation"
        aria-label="{{ __('ui.nav.main_navigation') }}"
        class="fixed top-0 inset-x-0 z-40 border-b border-white/10 {{ $navSurface }} [&>div]:mx-auto [&>div]:max-w-7xl [&>div]:min-h-16 [&>div]:!py-0 [&>div]:px-4 sm:[&>div]:px-6 lg:[&>div]:px-8"
    >
        <x-slot:brand>
            <div class="flex">
                <div class="flex shrink-0 items-center">
                    <x-brand
                        size="nav"
                        :href="$brandUrl"
                        wire:navigate
                        class="ui-nav-brand shrink-0"
                    />
                </div>

                <div class="hidden space-x-4 sm:-my-px sm:ms-10 sm:flex">
                    <a href="{{ BrowseSearchState::indexUrl() }}" wire:navigate
                       class="{{ $navLink(request()->routeIs('search.index')) }} gap-1.5">
                        <x-icon name="o-magnifying-glass" class="h-4 w-4 shrink-0" />
                        {{ __('ui.nav.search') }}
                    </a>
                    <a href="{{ route('catalog.places') }}" wire:navigate
                       class="{{ $navLink(request()->routeIs('catalog.places')) }} gap-1.5">
                        <x-icon name="o-map-pin" class="h-4 w-4 shrink-0" />
                        {{ __('ui.nav.places') }}
                    </a>
                    <a href="{{ route('catalog.organizations') }}" wire:navigate
                       class="{{ $navLink(request()->routeIs('catalog.organizations')) }} gap-1.5">
                        <x-icon name="o-building-office-2" class="h-4 w-4 shrink-0" />
                        {{ __('ui.nav.organizations') }}
                    </a>
                    <a href="{{ route('catalog.series') }}" wire:navigate
                       class="{{ $navLink(request()->routeIs('catalog.series')) }} gap-1.5">
                        <x-icon name="o-rectangle-stack" class="h-4 w-4 shrink-0" />
                        {{ __('ui.nav.series') }}
                    </a>
                </div>
            </div>
        </x-slot:brand>

        <x-slot:actions class="!gap-2 sm:!gap-2">
            <div class="hidden sm:ms-6 sm:flex sm:items-center sm:gap-2">
                <x-locale-toggle />
                <x-theme-toggle class="btn btn-circle btn-ghost"/>
                @guest
                    <x-button :link="route('login')" class="btn-ghost btn-sm font-display">
                        {{ __('ui.nav.log_in') }}
                    </x-button>

                    @if (Route::has('register'))
                        <x-button :link="route('register')" class="btn-primary btn-sm font-display">
                            {{ __('ui.nav.register') }}
                        </x-button>
                    @endif
                @endguest
                @auth
                <livewire:user-requests.user-request-dropdown
                    variant="desktop"
                    :key="'nav-requests-desktop-'.auth()->id()"
                />
                <livewire:notifications.notification-dropdown
                    variant="desktop"
                    :key="'nav-notifications-desktop-'.auth()->id()"
                />

                <div class="dropdown dropdown-end relative z-50">
                    <div tabindex="0" role="button" class="btn btn-ghost btn-circle border border-base-300 p-0 light:border-neutral">
                        <x-user-badge
                            :user="auth()->user()"
                            :avatar-url="$navAvatarUrl"
                            size="sm"
                            avatar-only
                            track-nav-avatar
                            :contact-popover="false"
                        />
                    </div>
                    <ul tabindex="0" class="menu dropdown-content z-[100] mt-3 w-56 rounded-box border border-base-300 bg-base-100 p-2 shadow-lg light:border-neutral">
                        <li class="mb-2 border-b border-base-300 px-2 pb-2 light:border-neutral">
                            <div class="-mx-1 block rounded-lg px-1 py-0.5">
                                <p class="text-sm font-semibold" x-data="{{ json_encode(['name' => auth()->user()->displayName()]) }}" x-text="name" x-on:profile-updated.window="name = $event.detail.name"></p>
                                <p class="text-xs opacity-70">{{ auth()->user()->email }}</p>
                            </div>
                        </li>
                        <li>
                            <a
                                wire:navigate
                                href="{{ route('profile') }}"
                                data-ui="nav-account-settings"
                            >
                                <x-icon name="o-cog-6-tooth" class="h-4 w-4 shrink-0" />
                                {{ __('ui.nav.account_settings') }}
                            </a>
                        </li>
                        @if (auth()->user()->canCreateEvents())
                            <li>
                                <a wire:navigate href="{{ route('organizations.index') }}">
                                    <x-icon name="o-building-office-2" class="h-4 w-4 shrink-0" />
                                    {{ __('ui.nav.my_organizations') }}
                                </a>
                            </li>
                        @endif
                        @if (auth()->user()->canCreateEvents())
                            <li>
                                <a wire:navigate href="{{ BrowseSearchUrl::myEvents() }}">
                                    <x-icon name="o-calendar-days" class="h-4 w-4 shrink-0" />
                                    {{ __('ui.me.menu_events') }}
                                </a>
                            </li>
                        @else
                            <li>
                                <button
                                    type="button"
                                    wire:click="openOrganizerRequestModal"
                                    class="w-full cursor-pointer text-left"
                                    data-ui="nav-request-organizer"
                                >
                                    <x-icon name="o-hand-raised" class="h-4 w-4 shrink-0" />
                                    {{ __('ui.user_requests.request_organizer_access') }}
                                </button>
                            </li>
                        @endif
                        <li>
                            <a wire:navigate href="{{ BrowseSearchUrl::myActivities() }}">
                                <x-icon name="o-puzzle-piece" class="h-4 w-4 shrink-0" />
                                {{ __('ui.me.menu_activities') }}
                            </a>
                        </li>
                        @if (auth()->user()->canCreateEvents())
                            <li>
                                <a wire:navigate href="{{ url_with_return(route('events.create')) }}">
                                    <x-icon name="o-plus-circle" class="h-4 w-4 shrink-0" />
                                    {{ __('ui.nav.create_event') }}
                                </a>
                            </li>
                        @endif
                        <li>
                            <a wire:navigate href="{{ url_with_return(route('activities.create')) }}">
                                <x-icon name="o-plus" class="h-4 w-4 shrink-0" />
                                {{ __('ui.nav.create_activity') }}
                            </a>
                        </li>
                        @if (auth()->user()->is_admin)
                            <li class="menu-title mt-1 border-t border-base-300 pt-2 light:border-neutral">
                                <span class="text-xs font-semibold uppercase tracking-wide text-base-content/50">
                                    {{ __('ui.nav.admin_section') }}
                                </span>
                            </li>
                            @include('livewire.layout.partials.admin-ops-menu-items')
                        @endif
                        <li>
                            <button type="button" wire:click="logout">
                                <x-icon name="o-arrow-right-on-rectangle" class="h-4 w-4 shrink-0" />
                                {{ __('ui.nav.log_out') }}
                            </button>
                        </li>
                    </ul>
                </div>
                @endauth
            </div>

            <div class="-me-2 flex items-center gap-1 sm:hidden">
                @auth
                    <livewire:user-requests.user-request-dropdown
                        variant="mobile"
                        :key="'nav-requests-mobile-'.auth()->id()"
                    />
                    <livewire:notifications.notification-dropdown
                        variant="mobile"
                        :key="'nav-notifications-mobile-'.auth()->id()"
                    />
                @endauth
                <x-button
                    type="button"
                    x-ref="menuToggle"
                    @click="toggle()"
                    x-bind:aria-expanded="open"
                    aria-controls="mobile-nav-drawer"
                    aria-label="{{ __('ui.nav.open_menu') }}"
                    class="btn-ghost btn-square rounded-md opacity-70 transition duration-150 ease-in-out hover:bg-base-200 hover:opacity-100 focus:outline-none"
                >
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </x-button>
            </div>
        </x-slot:actions>
    </x-nav>

    <div class="ui-app-navigation__spacer shrink-0" aria-hidden="true"></div>

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
                id="mobile-nav-drawer"
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
                @auth
                    <div class="border-b border-base-300 bg-base-200/40 px-4 py-4">
                        <x-user-badge
                            :user="auth()->user()"
                            :avatar-url="$navAvatarUrl"
                            size="lg"
                            :subline="auth()->user()->email"
                            track-nav-avatar
                            :contact-popover="false"
                        />
                        <div class="mt-3 flex flex-wrap items-center gap-2">
                            <x-locale-toggle @click="close()" />
                            <x-theme-toggle class="btn btn-ghost btn-sm" />
                        </div>
                    </div>
                @else
                    <div class="border-b border-base-300 bg-base-200/40 px-4 py-4">
                        <div class="flex flex-wrap items-center gap-2">
                            <x-locale-toggle @click="close()" />
                            <x-theme-toggle class="btn btn-ghost btn-sm" />
                        </div>
                    </div>
                @endauth

                <div class="flex-1 overflow-y-auto">
                    <p class="px-4 pt-4 text-xs font-semibold uppercase tracking-wide text-base-content/50">
                        {{ __('ui.nav.navigation') }}
                    </p>
                    <ul class="menu menu-lg w-full px-2">
                        <li>
                            <a
                                href="{{ BrowseSearchState::indexUrl() }}"
                                wire:navigate
                                @click="close()"
                                class="{{ $mobileNavLink(request()->routeIs('search.index')) }}"
                            >
                                <x-icon name="o-magnifying-glass" class="h-4 w-4 shrink-0" />
                                {{ __('ui.nav.search') }}
                            </a>
                        </li>
                        <li>
                            <a
                                href="{{ route('catalog.places') }}"
                                wire:navigate
                                @click="close()"
                                class="{{ $mobileNavLink(request()->routeIs('catalog.places')) }}"
                            >
                                <x-icon name="o-map-pin" class="h-4 w-4 shrink-0" />
                                {{ __('ui.nav.places') }}
                            </a>
                        </li>
                        <li>
                            <a
                                href="{{ route('catalog.organizations') }}"
                                wire:navigate
                                @click="close()"
                                class="{{ $mobileNavLink(request()->routeIs('catalog.organizations')) }}"
                            >
                                <x-icon name="o-building-office-2" class="h-4 w-4 shrink-0" />
                                {{ __('ui.nav.organizations') }}
                            </a>
                        </li>
                        <li>
                            <a
                                href="{{ route('catalog.series') }}"
                                wire:navigate
                                @click="close()"
                                class="{{ $mobileNavLink(request()->routeIs('catalog.series')) }}"
                            >
                                <x-icon name="o-rectangle-stack" class="h-4 w-4 shrink-0" />
                                {{ __('ui.nav.series') }}
                            </a>
                        </li>
                    </ul>

                    @auth
                        <div class="border-t border-base-300 px-4 pb-4 pt-2">
                            <p class="mb-1 px-2 text-xs font-semibold uppercase tracking-wide text-base-content/50">
                                {{ __('ui.nav.account') }}
                            </p>
                            <ul class="menu menu-lg w-full px-0">
                                <li>
                                    <a
                                        href="{{ route('profile') }}"
                                        wire:navigate
                                        @click="close()"
                                        class="{{ $mobileNavLink(request()->routeIs('profile')) }}"
                                        data-ui="nav-account-settings"
                                    >
                                        <x-icon name="o-cog-6-tooth" class="h-4 w-4 shrink-0" />
                                        {{ __('ui.nav.account_settings') }}
                                    </a>
                                </li>
                                @if (auth()->user()->canCreateEvents())
                                    <li>
                                        <a
                                            href="{{ route('organizations.index') }}"
                                            wire:navigate
                                            @click="close()"
                                            class="{{ $mobileNavLink(request()->routeIs('organizations.index')) }}"
                                        >
                                            <x-icon name="o-building-office-2" class="h-4 w-4 shrink-0" />
                                            {{ __('ui.nav.my_organizations') }}
                                        </a>
                                    </li>
                                @endif
                                @if (auth()->user()->canCreateEvents())
                                    <li>
                                        <a
                                            href="{{ BrowseSearchUrl::myEvents() }}"
                                            wire:navigate
                                            @click="close()"
                                            class="{{ $mobileNavLink(BrowseSearchUrl::isMyEvents(request())) }}"
                                        >
                                            <x-icon name="o-calendar-days" class="h-4 w-4 shrink-0" />
                                            {{ __('ui.me.menu_events') }}
                                        </a>
                                    </li>
                                @else
                                    <li>
                                        <button
                                            type="button"
                                            wire:click="openOrganizerRequestModal"
                                            @click="close()"
                                            class="w-full cursor-pointer text-left"
                                            data-ui="nav-request-organizer"
                                        >
                                            <x-icon name="o-hand-raised" class="h-4 w-4 shrink-0" />
                                            {{ __('ui.user_requests.request_organizer_access') }}
                                        </button>
                                    </li>
                                @endif
                                <li>
                                    <a
                                        href="{{ BrowseSearchUrl::myActivities() }}"
                                        wire:navigate
                                        @click="close()"
                                        class="{{ $mobileNavLink(BrowseSearchUrl::isMyActivities(request())) }}"
                                    >
                                        <x-icon name="o-puzzle-piece" class="h-4 w-4 shrink-0" />
                                        {{ __('ui.me.menu_activities') }}
                                    </a>
                                </li>
                                @if (auth()->user()->canCreateEvents())
                                    <li>
                                        <a
                                            href="{{ url_with_return(route('events.create')) }}"
                                            wire:navigate
                                            @click="close()"
                                            class="{{ $mobileNavLink(request()->routeIs('events.create')) }}"
                                        >
                                            <x-icon name="o-plus-circle" class="h-4 w-4 shrink-0" />
                                            {{ __('ui.nav.create_event') }}
                                        </a>
                                    </li>
                                @endif
                                <li>
                                    <a
                                        href="{{ url_with_return(route('activities.create')) }}"
                                        wire:navigate
                                        @click="close()"
                                        class="{{ $mobileNavLink(request()->routeIs('activities.create')) }}"
                                    >
                                        <x-icon name="o-plus" class="h-4 w-4 shrink-0" />
                                        {{ __('ui.nav.create_activity') }}
                                    </a>
                                </li>
                                @if (auth()->user()->is_admin)
                                    <li class="menu-title mt-1 border-t border-base-300 pt-2 light:border-neutral">
                                        <span class="text-xs font-semibold uppercase tracking-wide text-base-content/50">
                                            {{ __('ui.nav.admin_section') }}
                                        </span>
                                    </li>
                                    @include('livewire.layout.partials.admin-ops-menu-items', ['closeOnClick' => true])
                                @endif
                                <li>
                                    <button type="button" wire:click="logout" @click="close()">
                                        <x-icon name="o-arrow-right-on-rectangle" class="h-4 w-4 shrink-0" />
                                        {{ __('Log Out') }}
                                    </button>
                                </li>
                            </ul>
                        </div>
                    @endauth

                    @guest
                        <div class="border-t border-base-300 px-4 pb-4 pt-2">
                            <p class="mb-1 px-2 text-xs font-semibold uppercase tracking-wide text-base-content/50">
                                {{ __('ui.nav.account') }}
                            </p>
                            <ul class="menu menu-lg w-full px-0">
                                <li>
                                    <a
                                        href="{{ route('login') }}"
                                        @click="close()"
                                        class="font-display"
                                    >
                                        <x-icon name="o-arrow-left-on-rectangle" class="h-4 w-4 shrink-0" />
                                        {{ __('ui.nav.log_in') }}
                                    </a>
                                </li>
                                @if (Route::has('register'))
                                    <li>
                                        <a
                                            href="{{ route('register') }}"
                                            @click="close()"
                                            class="font-display"
                                        >
                                            <x-icon name="o-user-plus" class="h-4 w-4 shrink-0" />
                                            {{ __('ui.nav.register') }}
                                        </a>
                                    </li>
                                @endif
                            </ul>
                        </div>
                    @endguest
                </div>
            </aside>
        </div>
    </template>

    @auth
        @if (! auth()->user()->canCreateEvents())
            <livewire:user-requests.send-user-request
                type="event_organizer_flag"
                :show-trigger="false"
                :key="'nav-organizer-request-modal-'.auth()->id()"
            />
        @endif
    @endauth
</div>
