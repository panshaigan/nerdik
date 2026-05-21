<?php

use App\Livewire\Actions\Logout;
use App\Support\Browse\BrowseSearchUrl;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

new class extends Component
{
    #[On('database-notifications-updated')]
    public function refreshNotificationIndicators(): void
    {
        //
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
@endphp

<div x-data="{ open: false }" class="ui-app-navigation relative z-40 border-b border-base-300">
    <x-nav
        sticky
        full-width
        role="navigation"
        aria-label="{{ __('Main navigation') }}"
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
                        {{ __('Dashboard') }}
                    </a>
                    <a href="{{ route('search.index') }}" wire:navigate
                       class="{{ $navLink(request()->routeIs('search.index')) }} inline-flex items-center border-b-2 px-1 pt-1 text-sm font-medium transition">
                        {{ __('ui.nav.search') }}
                    </a>
                </div>
            </div>
        </x-slot:brand>

        <x-slot:actions class="!gap-2 sm:!gap-2">
            <div class="hidden sm:ms-6 sm:flex sm:items-center sm:gap-2">
                <a
                   href="{{ route('locale.switch', ['locale' => 'en']) }}"
                   @click.prevent="window.location.href = '{{ route('locale.switch', ['locale' => 'en']) }}?redirect=' + encodeURIComponent(window.location.pathname + window.location.search + window.location.hash)"
                   class="btn btn-circle btn-ghost {{ app()->getLocale() === 'en' ? 'text-primary-content' : 'text-neutral' }}">
                    {{ __('EN') }}
                </a>
                <a
                   href="{{ route('locale.switch', ['locale' => 'pl']) }}"
                   @click.prevent="window.location.href = '{{ route('locale.switch', ['locale' => 'pl']) }}?redirect=' + encodeURIComponent(window.location.pathname + window.location.search + window.location.hash)"
                   class="btn btn-circle btn-ghost {{ app()->getLocale() === 'pl' ? 'text-primary-content' : 'text-neutral' }}">
                    {{ __('PL') }}
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
                        <x-user-badge :user="auth()->user()" size="sm" avatar-only />
                    </div>
                    <ul tabindex="0" class="menu dropdown-content z-[100] mt-3 w-56 rounded-box border border-base-300 bg-base-100 p-2 shadow-lg">
                        <li class="mb-2 border-b border-base-300 px-2 pb-2">
                            <p class="text-sm font-semibold" x-data="{{ json_encode(['name' => auth()->user()->displayName()]) }}" x-text="name" x-on:profile-updated.window="name = $event.detail.name"></p>
                            <p class="text-xs opacity-70">{{ auth()->user()->email }}</p>
                        </li>
                        <li>
                            <a wire:navigate href="{{ route('notifications.index') }}">
                                {{ __('Notifications') }}
                                @if (auth()->user()->unreadNotifications->count() > 0)
                                    <span class="badge badge-sm badge-error">{{ auth()->user()->unreadNotifications->count() }}</span>
                                @endif
                            </a>
                        </li>
                        <li><a wire:navigate href="{{ route('profile') }}">{{ __('Profile') }}</a></li>
                        <li><a wire:navigate href="{{ route('organizations.index') }}">{{ __('Organizations') }}</a></li>
                        <li><a wire:navigate href="{{ BrowseSearchUrl::myEvents() }}">{{ __('ui.me.menu_events') }}</a></li>
                        <li><a wire:navigate href="{{ BrowseSearchUrl::myActivities() }}">{{ __('ui.me.menu_activities') }}</a></li>
                        <li>
                            <button type="button" wire:click="logout">{{ __('Log Out') }}</button>
                        </li>
                    </ul>
                </div>
                @endauth
            </div>

            <div class="-me-2 flex items-center sm:hidden">
                <x-button type="button" @click="open = ! open" class="btn-ghost btn-square rounded-md opacity-70 transition duration-150 ease-in-out hover:bg-base-200 hover:opacity-100 focus:outline-none">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </x-button>
            </div>
        </x-slot:actions>
    </x-nav>

    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden">
        <div class="space-y-1 pb-3 pt-2">
            <a href="{{ route('dashboard') }}" wire:navigate
               class="{{ $navLink(request()->routeIs('dashboard')) }} block border-l-4 py-2 ps-3 pe-4 text-base font-medium">
                {{ __('Dashboard') }}
            </a>
            <a href="{{ route('search.index') }}" wire:navigate
               class="{{ $navLink(request()->routeIs('search.index')) }} block border-l-4 py-2 ps-3 pe-4 text-base font-medium">
                {{ __('ui.nav.search') }}
            </a>
        </div>

        <div class="border-t border-base-300 pb-1 pt-4">
            @auth
            <div class="px-4">
                <div class="text-base font-medium" x-data="{{ json_encode(['name' => auth()->user()->displayName()]) }}" x-text="name" x-on:profile-updated.window="name = $event.detail.name"></div>
                <div class="text-sm font-medium opacity-70">{{ auth()->user()->email }}</div>
            </div>
            @endauth

            <div class="mt-3 space-y-1">
                <div class="px-4 py-2">
                    <p class="text-xs uppercase tracking-wide opacity-70">{{ __('ui.common.language') }}</p>
                    <div class="mt-2 flex gap-2">
                        <a
                           href="{{ route('locale.switch', ['locale' => 'en']) }}"
                           @click.prevent="window.location.href = '{{ route('locale.switch', ['locale' => 'en']) }}?redirect=' + encodeURIComponent(window.location.pathname + window.location.search + window.location.hash)"
                           class="btn btn-circle btn-ghost {{ app()->getLocale() === 'en' ? 'border-primary bg-primary text-primary-content' : '' }}">
                            {{ __('EN') }}
                        </a>
                        <a
                           href="{{ route('locale.switch', ['locale' => 'pl']) }}"
                           @click.prevent="window.location.href = '{{ route('locale.switch', ['locale' => 'pl']) }}?redirect=' + encodeURIComponent(window.location.pathname + window.location.search + window.location.hash)"
                           class="btn btn-circle btn-ghost {{ app()->getLocale() === 'pl' ? 'border-primary bg-primary text-primary-content' : '' }}">
                            {{ __('PL') }}
                        </a>
                    </div>
                </div>
                <a href="#"
                    type="button"
                    onclick="window.toggleTheme()"
                    class="block border-l-4 border-transparent py-2 ps-3 pe-4 text-base font-medium text-base-content/80"
                >
                    {{ __('Switch Theme') }}
                </a>
                @auth
                <a href="{{ route('notifications.index') }}" wire:navigate
                   class="block border-l-4 border-transparent py-2 ps-3 pe-4 text-base font-medium text-base-content/80">
                    {{ __('Notifications') }}
                    @if (auth()->user()->unreadNotifications->count() > 0)
                        <span class="badge badge-error badge-sm">{{ auth()->user()->unreadNotifications->count() }}</span>
                    @endif
                </a>
                <a href="{{ route('profile') }}" wire:navigate
                   class="block border-l-4 border-transparent py-2 ps-3 pe-4 text-base font-medium text-base-content/80">
                    {{ __('Profile') }}
                </a>
                <a href="{{ route('organizations.index') }}" wire:navigate
                   class="{{ $navLink(request()->routeIs('organizations.index')) }} block border-l-4 py-2 ps-3 pe-4 text-base font-medium">
                    {{ __('Organizations') }}
                </a>
                <a href="{{ BrowseSearchUrl::myEvents() }}" wire:navigate
                   class="{{ $navLink(BrowseSearchUrl::isMyEvents(request())) }} block border-l-4 py-2 ps-3 pe-4 text-base font-medium">
                    {{ __('ui.me.menu_events') }}
                </a>
                <a href="{{ BrowseSearchUrl::myActivities() }}" wire:navigate
                   class="{{ $navLink(BrowseSearchUrl::isMyActivities(request())) }} block border-l-4 py-2 ps-3 pe-4 text-base font-medium">
                    {{ __('ui.me.menu_activities') }}
                </a>
                <button type="button" wire:click="logout"
                        class="block w-full border-l-4 border-transparent py-2 ps-3 pe-4 text-start text-base font-medium text-base-content/80">
                    {{ __('Log Out') }}
                </button>
                @endauth
            </div>
        </div>
    </div>
</div>
