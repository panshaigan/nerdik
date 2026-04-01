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

@php
    $navLink = function (bool $active): string {
        return $active
            ? 'border-primary text-base-content'
            : 'border-transparent text-base-content/70 hover:border-base-300 hover:text-base-content';
    };
@endphp

<nav x-data="{ open: false }" class="border-b border-base-300 bg-base-100/90 backdrop-blur">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="flex h-16 justify-between">
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
                    <a href="{{ route('browse.events') }}" wire:navigate
                       class="{{ $navLink(request()->routeIs('browse.events')) }} inline-flex items-center border-b-2 px-1 pt-1 text-sm font-medium transition">
                        {{ __('Events') }}
                    </a>
                    <a href="{{ route('browse.activities') }}" wire:navigate
                       class="{{ $navLink(request()->routeIs('browse.activities')) }} inline-flex items-center border-b-2 px-1 pt-1 text-sm font-medium transition">
                        {{ __('Activities') }}
                    </a>
                    <a href="{{ route('browse.organizations') }}" wire:navigate
                       class="{{ $navLink(request()->routeIs('browse.organizations')) }} inline-flex items-center border-b-2 px-1 pt-1 text-sm font-medium transition">
                        {{ __('Organizations') }}
                    </a>
                </div>
            </div>

            <div class="hidden sm:ms-6 sm:flex sm:items-center sm:gap-2">
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

                <div class="dropdown dropdown-end">
                    <div tabindex="0" role="button" class="btn btn-ghost btn-circle avatar border border-base-300">
                        <div class="w-9 rounded-full">
                            <img
                                src="https://ui-avatars.com/api/?name={{ urlencode(auth()->user()->name ?? 'User') }}&background=334155&color=ffffff&bold=true"
                                alt=""
                            />
                        </div>
                    </div>
                    <ul tabindex="0" class="menu dropdown-content z-[100] mt-3 w-56 rounded-box border border-base-300 bg-base-100 p-2 shadow-lg">
                        <li class="mb-2 border-b border-base-300 px-2 pb-2">
                            <p class="text-sm font-semibold" x-data="{{ json_encode(['name' => auth()->user()->name]) }}" x-text="name" x-on:profile-updated.window="name = $event.detail.name"></p>
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
                        <li><a href="#">{{ __('Dummy menu item') }}</a></li>
                        <li><a href="#">{{ __('Another quick action') }}</a></li>
                        <li>
                            <button type="button" wire:click="logout" class="w-full text-start">{{ __('Log Out') }}</button>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center rounded-md p-2 opacity-70 transition duration-150 ease-in-out hover:bg-base-200 hover:opacity-100 focus:outline-none">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden">
        <div class="space-y-1 pb-3 pt-2">
            <a href="{{ route('dashboard') }}" wire:navigate
               class="{{ $navLink(request()->routeIs('dashboard')) }} block border-l-4 py-2 ps-3 pe-4 text-base font-medium">
                {{ __('Dashboard') }}
            </a>
            <a href="{{ route('browse.events') }}" wire:navigate
               class="{{ $navLink(request()->routeIs('browse.events')) }} block border-l-4 py-2 ps-3 pe-4 text-base font-medium">
                {{ __('Events') }}
            </a>
            <a href="{{ route('browse.activities') }}" wire:navigate
               class="{{ $navLink(request()->routeIs('browse.activities')) }} block border-l-4 py-2 ps-3 pe-4 text-base font-medium">
                {{ __('Activities') }}
            </a>
            <a href="{{ route('browse.organizations') }}" wire:navigate
               class="{{ $navLink(request()->routeIs('browse.organizations')) }} block border-l-4 py-2 ps-3 pe-4 text-base font-medium">
                {{ __('Organizations') }}
            </a>
        </div>

        <div class="border-t border-base-300 pb-1 pt-4">
            <div class="px-4">
                <div class="text-base font-medium" x-data="{{ json_encode(['name' => auth()->user()->name]) }}" x-text="name" x-on:profile-updated.window="name = $event.detail.name"></div>
                <div class="text-sm font-medium opacity-70">{{ auth()->user()->email }}</div>
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
                <a href="{{ route('notifications.index') }}" wire:navigate
                   class="block border-l-4 border-transparent py-2 ps-3 pe-4 text-base font-medium text-base-content/80">
                    {{ __('Notifications') }}
                    @if (auth()->user()->unreadNotifications->count() > 0)
                        <span class="rounded-full bg-red-500 px-1.5 py-0.5 text-xs text-white">{{ auth()->user()->unreadNotifications->count() }}</span>
                    @endif
                </a>
                <a href="{{ route('profile') }}" wire:navigate
                   class="block border-l-4 border-transparent py-2 ps-3 pe-4 text-base font-medium text-base-content/80">
                    {{ __('Profile') }}
                </a>
                <button type="button" wire:click="logout" class="w-full border-l-4 border-transparent py-2 ps-3 pe-4 text-start text-base font-medium text-base-content/80">
                    {{ __('Log Out') }}
                </button>
            </div>
        </div>
    </div>
</nav>
