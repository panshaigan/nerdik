<?php

use App\Livewire\Actions\Logout;
use Livewire\Volt\Component;

new class extends Component
{
    /**
     * Log the current user out of the application.
     */
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: true);
    }
}; ?>

<nav x-data="{ open: false }" class="border-b border-base-300 bg-base-100/90 backdrop-blur">
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('dashboard') }}" wire:navigate>
                        <x-application-logo class="block h-9 w-auto fill-current text-base-content" />
                    </a>
                </div>

                <!-- Navigation Links -->
                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                    <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')" wire:navigate>
                        {{ __('Dashboard') }}
                    </x-nav-link>
                    <x-nav-link :href="route('events.index')" :active="request()->routeIs('events.*')" wire:navigate>
                        {{ __('Events') }}
                    </x-nav-link>
                    <x-nav-link :href="route('browse.events')" :active="request()->routeIs('browse.events')" wire:navigate>
                        {{ __('Events') }}
                    </x-nav-link>
                    <x-nav-link :href="route('browse.activities')" :active="request()->routeIs('browse.activities')" wire:navigate>
                        {{ __('Activities') }}
                    </x-nav-link>
                </div>
            </div>

            <div class="hidden sm:flex sm:items-center sm:ms-6 sm:gap-2">
                <button type="button"
                        onclick="window.toggleTheme()"
                        class="rounded-md border border-base-300 bg-base-100 px-2 py-1 text-xs hover:bg-base-200">
                    <span>{{ __('Theme') }}</span>
                </button>
                <div class="inline-flex items-center rounded-md border border-base-300 bg-base-100 p-1 text-xs">
                    <span class="px-2 opacity-70">{{ __('ui.common.language') }}</span>
                    <a href="{{ route('locale.switch', ['locale' => 'en', 'redirect' => request()->getRequestUri()]) }}"
                       class="rounded px-2 py-1 {{ app()->getLocale() === 'en' ? 'bg-primary text-primary-content' : 'opacity-80 hover:bg-base-200' }}">
                        {{ __('ui.common.language_en') }}
                    </a>
                    <a href="{{ route('locale.switch', ['locale' => 'pl', 'redirect' => request()->getRequestUri()]) }}"
                       class="rounded px-2 py-1 {{ app()->getLocale() === 'pl' ? 'bg-primary text-primary-content' : 'opacity-80 hover:bg-base-200' }}">
                        {{ __('ui.common.language_pl') }}
                    </a>
                </div>
                <a href="{{ route('notifications.index') }}" wire:navigate class="relative rounded-md p-2 opacity-80 hover:bg-base-200 hover:opacity-100">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                    </svg>
                    @if (auth()->user()->unreadNotifications->count() > 0)
                        <span class="absolute top-1 right-1 flex h-4 w-4 items-center justify-center rounded-full bg-red-500 text-[10px] font-medium text-white">
                            {{ auth()->user()->unreadNotifications->count() > 9 ? '9+' : auth()->user()->unreadNotifications->count() }}
                        </span>
                    @endif
                </a>
                <x-dropdown align="right" width="56">
                    <x-slot name="trigger">
                        <button class="rounded-full border border-base-300 p-0.5 transition hover:border-primary focus:outline-none">
                            <img
                                src="https://ui-avatars.com/api/?name={{ urlencode(auth()->user()->name ?? 'User') }}&background=334155&color=ffffff&bold=true"
                                alt="Profile avatar"
                                class="h-9 w-9 rounded-full object-cover"
                            />
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <div class="px-4 py-3 border-b border-base-300">
                            <p class="text-sm font-semibold" x-data="{{ json_encode(['name' => auth()->user()->name]) }}" x-text="name" x-on:profile-updated.window="name = $event.detail.name"></p>
                            <p class="text-xs opacity-70">{{ auth()->user()->email }}</p>
                        </div>
                        <x-dropdown-link :href="route('notifications.index')" wire:navigate>
                            {{ __('Notifications') }}
                            @if (auth()->user()->unreadNotifications->count() > 0)
                                <span class="ms-1 rounded-full bg-red-500 px-1.5 py-0.5 text-xs text-white">
                                    {{ auth()->user()->unreadNotifications->count() }}
                                </span>
                            @endif
                        </x-dropdown-link>
                        <x-dropdown-link :href="route('profile')" wire:navigate>
                            {{ __('Profile') }}
                        </x-dropdown-link>
                        <x-dropdown-link href="#">
                            {{ __('Dummy menu item') }}
                        </x-dropdown-link>
                        <x-dropdown-link href="#">
                            {{ __('Another quick action') }}
                        </x-dropdown-link>

                        <!-- Authentication -->
                        <button wire:click="logout" class="w-full text-start">
                            <x-dropdown-link>
                                {{ __('Log Out') }}
                            </x-dropdown-link>
                        </button>
                    </x-slot>
                </x-dropdown>
            </div>

            <!-- Hamburger -->
            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center rounded-md p-2 opacity-70 hover:bg-base-200 hover:opacity-100 focus:outline-none transition duration-150 ease-in-out">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden">
        <div class="pt-2 pb-3 space-y-1">
            <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')" wire:navigate>
                {{ __('Dashboard') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('events.index')" :active="request()->routeIs('events.*')" wire:navigate>
                {{ __('Events') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('browse.events')" :active="request()->routeIs('browse.events')" wire:navigate>
                {{ __('Events') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('browse.activities')" :active="request()->routeIs('browse.activities')" wire:navigate>
                {{ __('Activities') }}
            </x-responsive-nav-link>
        </div>

        <!-- Responsive Settings Options -->
        <div class="border-t border-base-300 pt-4 pb-1">
            <div class="px-4">
                <div class="font-medium text-base" x-data="{{ json_encode(['name' => auth()->user()->name]) }}" x-text="name" x-on:profile-updated.window="name = $event.detail.name"></div>
                <div class="font-medium text-sm opacity-70">{{ auth()->user()->email }}</div>
            </div>

            <div class="mt-3 space-y-1">
                <div class="px-4 py-2">
                    <button type="button"
                            onclick="window.toggleTheme()"
                            class="mb-3 rounded border border-base-300 px-2 py-1 text-xs">
                        {{ __('Theme') }}
                    </button>
                    <p class="text-xs uppercase tracking-wide opacity-70">{{ __('ui.common.language') }}</p>
                    <div class="mt-2 flex gap-2">
                        <a href="{{ route('locale.switch', ['locale' => 'en', 'redirect' => request()->getRequestUri()]) }}"
                           class="rounded border px-2 py-1 text-xs {{ app()->getLocale() === 'en' ? 'border-primary bg-primary text-primary-content' : 'border-base-300' }}">
                            {{ __('ui.common.language_en') }}
                        </a>
                        <a href="{{ route('locale.switch', ['locale' => 'pl', 'redirect' => request()->getRequestUri()]) }}"
                           class="rounded border px-2 py-1 text-xs {{ app()->getLocale() === 'pl' ? 'border-primary bg-primary text-primary-content' : 'border-base-300' }}">
                            {{ __('ui.common.language_pl') }}
                        </a>
                    </div>
                </div>
                <x-responsive-nav-link :href="route('notifications.index')" wire:navigate>
                    {{ __('Notifications') }}
                    @if (auth()->user()->unreadNotifications->count() > 0)
                        <span class="rounded-full bg-red-500 px-1.5 py-0.5 text-xs text-white">{{ auth()->user()->unreadNotifications->count() }}</span>
                    @endif
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('profile')" wire:navigate>
                    {{ __('Profile') }}
                </x-responsive-nav-link>

                <!-- Authentication -->
                <button wire:click="logout" class="w-full text-start">
                    <x-responsive-nav-link>
                        {{ __('Log Out') }}
                    </x-responsive-nav-link>
                </button>
            </div>
        </div>
    </div>
</nav>
