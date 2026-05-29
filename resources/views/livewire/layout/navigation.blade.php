<?php

use App\Livewire\Actions\Logout;
use App\Support\Browse\BrowseSearchUrl;
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

    #[On('profile-avatar-updated')]
    public function refreshNavigationAvatar(string $avatarUrl): void
    {
        $this->navAvatarUrl = $avatarUrl;
    }

    /**
     * Log the current user out of the application.
     */
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: true);
    }
}; ?>

@php
    $navLink = function (bool $active): string {
        return $active
            ? 'border-primary text-base-content'
            : 'border-transparent text-base-content/70 hover:border-base-300 hover:text-base-content';
    };

    $mobileNavLink = fn (bool $active): string => $active ? 'active font-medium' : '';
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
    class="ui-app-navigation relative z-40 border-b border-base-300"
>
    <x-nav
        sticky
        full-width
        role="navigation"
        aria-label="{{ __('ui.nav.main_navigation') }}"
        class="relative z-40 !border-b-0 bg-base-100/90 backdrop-blur [&>div]:mx-auto [&>div]:max-w-7xl [&>div]:min-h-16 [&>div]:!py-0 [&>div]:px-4 sm:[&>div]:px-6 lg:[&>div]:px-8"
    >
        <x-slot:brand>
            <div class="flex">
                <div class="flex shrink-0 items-center">
                    <a href="{{ route('dashboard') }}" wire:navigate>
                        <x-brand-logo class="block h-9 w-auto fill-current text-base-content" />
                    </a>
                </div>

                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                    <a href="{{ route('dashboard') }}" wire:navigate
                       class="{{ $navLink(request()->routeIs('dashboard')) }} inline-flex items-center border-b-2 px-1 pt-1 text-sm font-medium transition">
                        {{ __('ui.nav.dashboard') }}
                    </a>
                    <a href="{{ route('search.index') }}" wire:navigate
                       class="{{ $navLink(request()->routeIs('search.index')) }} inline-flex items-center border-b-2 px-1 pt-1 text-sm font-medium transition">
                        {{ __('ui.nav.search') }}
                    </a>
                    @auth
                        @if (auth()->user()->canCreateEvents())
                            <a href="{{ url_with_return(route('events.create')) }}" wire:navigate
                               class="{{ $navLink(request()->routeIs('events.create')) }} inline-flex items-center border-b-2 px-1 pt-1 text-sm font-medium transition">
                                {{ __('ui.nav.create_event') }}
                            </a>
                        @endif
                        <a href="{{ url_with_return(route('activities.create')) }}" wire:navigate
                           class="{{ $navLink(request()->routeIs('activities.create')) }} inline-flex items-center border-b-2 px-1 pt-1 text-sm font-medium transition">
                            {{ __('ui.nav.create_activity') }}
                        </a>
                    @endauth
                </div>
            </div>
        </x-slot:brand>

        <x-slot:actions class="!gap-2 sm:!gap-2">
            <div class="hidden sm:ms-6 sm:flex sm:items-center sm:gap-2">
                <a
                   wire:navigate
                   x-bind:href="localeSwitchUrl('{{ route('locale.switch', ['locale' => 'en']) }}')"
                   class="btn btn-circle btn-ghost {{ app()->getLocale() === 'en' ? 'text-primary-content' : 'text-neutral' }}">
                    {{ __('ui.common.language_en') }}
                </a>
                <a
                   wire:navigate
                   x-bind:href="localeSwitchUrl('{{ route('locale.switch', ['locale' => 'pl']) }}')"
                   class="btn btn-circle btn-ghost {{ app()->getLocale() === 'pl' ? 'text-primary-content' : 'text-neutral' }}">
                    {{ __('ui.common.language_pl') }}
                </a>
                <x-theme-toggle class="btn btn-circle btn-ghost"/>
                @auth
                <a href="{{ route('notifications.index') }}" wire:navigate class="relative btn btn-circle btn-ghost">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                    </svg>
                    @if (auth()->user()->unreadNotifications->count() > 0)
                        <span class="absolute right-1 top-1 flex h-4 w-4 items-center justify-center rounded-full bg-error text-[10px] font-medium text-error-content">
                            {{ auth()->user()->unreadNotifications->count() > 9 ? '9+' : auth()->user()->unreadNotifications->count() }}
                        </span>
                    @endif
                </a>

                <div class="dropdown dropdown-end relative z-50">
                    <div tabindex="0" role="button" class="btn btn-ghost btn-circle border border-base-300 p-0">
                        <x-user-badge
                            :user="auth()->user()"
                            :avatar-url="$navAvatarUrl"
                            size="sm"
                            avatar-only
                            track-nav-avatar
                        />
                    </div>
                    <ul tabindex="0" class="menu dropdown-content z-[100] mt-3 w-56 rounded-box border border-base-300 bg-base-100 p-2 shadow-lg">
                        <li class="mb-2 border-b border-base-300 px-2 pb-2">
                            <a wire:navigate href="{{ route('profile') }}" class="-mx-1 block rounded-lg px-1 py-0.5 hover:bg-base-200">
                                <p class="text-sm font-semibold" x-data="{{ json_encode(['name' => auth()->user()->displayName()]) }}" x-text="name" x-on:profile-updated.window="name = $event.detail.name"></p>
                                <p class="text-xs opacity-70">{{ auth()->user()->email }}</p>
                            </a>
                        </li>
                        <li><a wire:navigate href="{{ route('organizations.index') }}">{{ __('ui.nav.organizations') }}</a></li>
                        <li><a wire:navigate href="{{ BrowseSearchUrl::myEvents() }}">{{ __('ui.me.menu_events') }}</a></li>
                        <li><a wire:navigate href="{{ BrowseSearchUrl::myActivities() }}">{{ __('ui.me.menu_activities') }}</a></li>
                        <li>
                            <button type="button" wire:click="logout">{{ __('ui.nav.log_out') }}</button>
                        </li>
                    </ul>
                </div>
                @endauth
            </div>

            <div class="-me-2 flex items-center gap-1 sm:hidden">
                @auth
                    <a
                        href="{{ route('notifications.index') }}"
                        wire:navigate
                        class="relative btn btn-ghost btn-square rounded-md opacity-70 transition duration-150 ease-in-out hover:bg-base-200 hover:opacity-100 focus:outline-none"
                        aria-label="{{ __('ui.nav.notifications') }}"
                    >
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                        </svg>
                        @if (auth()->user()->unreadNotifications->count() > 0)
                            <span class="absolute right-1 top-1 flex h-4 w-4 items-center justify-center rounded-full bg-error text-[10px] font-medium text-error-content">
                                {{ auth()->user()->unreadNotifications->count() > 9 ? '9+' : auth()->user()->unreadNotifications->count() }}
                            </span>
                        @endif
                    </a>
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
                        <a href="{{ route('profile') }}" wire:navigate @click="close()" class="block rounded-lg transition hover:bg-base-200/60">
                            <x-user-badge
                                :user="auth()->user()"
                                :avatar-url="$navAvatarUrl"
                                size="lg"
                                :subline="auth()->user()->email"
                                track-nav-avatar
                            />
                        </a>
                    </div>
                @endauth

                <div class="flex-1 overflow-y-auto">
                    <p class="px-4 pt-4 text-xs font-semibold uppercase tracking-wide text-base-content/50">
                        {{ __('ui.nav.navigation') }}
                    </p>
                    <ul class="menu menu-lg w-full px-2">
                        <li>
                            <a
                                href="{{ route('dashboard') }}"
                                wire:navigate
                                @click="close()"
                                class="{{ $mobileNavLink(request()->routeIs('dashboard')) }}"
                            >
                                {{ __('ui.nav.dashboard') }}
                            </a>
                        </li>
                        <li>
                            <a
                                href="{{ route('search.index') }}"
                                wire:navigate
                                @click="close()"
                                class="{{ $mobileNavLink(request()->routeIs('search.index')) }}"
                            >
                                {{ __('ui.nav.search') }}
                            </a>
                        </li>
                        @auth
                            @if (auth()->user()->canCreateEvents())
                                <li>
                                    <a
                                        href="{{ url_with_return(route('events.create')) }}"
                                        wire:navigate
                                        @click="close()"
                                        class="{{ $mobileNavLink(request()->routeIs('events.create')) }}"
                                    >
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
                                    {{ __('ui.nav.create_activity') }}
                                </a>
                            </li>
                        @endauth
                    </ul>

                    <div class="border-t border-base-300 px-4 py-4">
                        <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-base-content/50">
                            {{ __('ui.nav.preferences') }}
                        </p>
                        <div class="flex flex-wrap items-center gap-2">
                            <div class="join">
                                <a
                                    wire:navigate
                                    x-bind:href="localeSwitchUrl('{{ route('locale.switch', ['locale' => 'en']) }}')"
                                    @click="close()"
                                    class="join-item btn btn-sm {{ app()->getLocale() === 'en' ? 'btn-primary' : 'btn-ghost' }}"
                                >
                                    {{ __('ui.common.language_en') }}
                                </a>
                                <a
                                    wire:navigate
                                    x-bind:href="localeSwitchUrl('{{ route('locale.switch', ['locale' => 'pl']) }}')"
                                    @click="close()"
                                    class="join-item btn btn-sm {{ app()->getLocale() === 'pl' ? 'btn-primary' : 'btn-ghost' }}"
                                >
                                    {{ __('ui.common.language_pl') }}
                                </a>
                            </div>
                            <x-theme-toggle class="btn btn-ghost btn-sm" />
                        </div>
                    </div>

                    @auth
                        <div class="border-t border-base-300 px-4 pb-4 pt-2">
                            <p class="mb-1 px-2 text-xs font-semibold uppercase tracking-wide text-base-content/50">
                                {{ __('ui.nav.account') }}
                            </p>
                            <ul class="menu menu-lg w-full px-0">
                                <li>
                                    <a
                                        href="{{ route('organizations.index') }}"
                                        wire:navigate
                                        @click="close()"
                                        class="{{ $mobileNavLink(request()->routeIs('organizations.index')) }}"
                                    >
                                        {{ __('ui.nav.organizations') }}
                                    </a>
                                </li>
                                <li>
                                    <a
                                        href="{{ BrowseSearchUrl::myEvents() }}"
                                        wire:navigate
                                        @click="close()"
                                        class="{{ $mobileNavLink(BrowseSearchUrl::isMyEvents(request())) }}"
                                    >
                                        {{ __('ui.me.menu_events') }}
                                    </a>
                                </li>
                                <li>
                                    <a
                                        href="{{ BrowseSearchUrl::myActivities() }}"
                                        wire:navigate
                                        @click="close()"
                                        class="{{ $mobileNavLink(BrowseSearchUrl::isMyActivities(request())) }}"
                                    >
                                        {{ __('ui.me.menu_activities') }}
                                    </a>
                                </li>
                                <li>
                                    <button type="button" wire:click="logout" @click="close()">
                                        {{ __('Log Out') }}
                                    </button>
                                </li>
                            </ul>
                        </div>
                    @endauth
                </div>
            </aside>
        </div>
    </template>
</div>
