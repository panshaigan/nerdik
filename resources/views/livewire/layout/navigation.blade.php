<?php

use App\Livewire\Actions\Logout;
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
                    @include('livewire.layout.partials.nav-primary-links', ['variant' => 'bar'])
                </div>
            </div>
        </x-slot:brand>

        <x-slot:actions class="!gap-2 sm:!gap-2">
            <div class="flex items-center gap-1 sm:ms-6 sm:gap-2">
                <div class="hidden sm:flex sm:items-center sm:gap-2">
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
                </div>
                @auth
                    <livewire:user-requests.user-request-dropdown
                        :key="'nav-requests-'.auth()->id()"
                    />
                    <livewire:notifications.notification-dropdown
                        :key="'nav-notifications-'.auth()->id()"
                    />
                    <div class="dropdown dropdown-end relative z-50 hidden sm:block">
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
                                <a
                                    wire:navigate
                                    href="{{ route('profile') }}"
                                    data-ui="nav-account-header"
                                    class="-mx-1 block rounded-lg px-1 py-0.5"
                                >
                                    <p class="text-sm font-semibold" x-data="{{ json_encode(['name' => auth()->user()->displayName()]) }}" x-text="name" x-on:profile-updated.window="name = $event.detail.name"></p>
                                    <p class="text-xs opacity-70">{{ auth()->user()->email }}</p>
                                </a>
                            </li>
                            @include('livewire.layout.partials.nav-account-menu-items', ['variant' => 'dropdown'])
                        </ul>
                    </div>
                @endauth
                <div class="-me-2 flex items-center sm:hidden">
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

            <div
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
                        @include('livewire.layout.partials.nav-primary-links', [
                            'variant' => 'drawer',
                            'closeOnClick' => true,
                        ])
                    </ul>

                    @auth
                        <div class="border-t border-base-300 px-4 pb-4 pt-2">
                            <p class="mb-1 px-2 text-xs font-semibold uppercase tracking-wide text-base-content/50">
                                {{ __('ui.nav.account') }}
                            </p>
                            <ul class="menu menu-lg w-full px-0">
                                @include('livewire.layout.partials.nav-account-menu-items', [
                                    'variant' => 'drawer',
                                    'closeOnClick' => true,
                                ])
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
            </div>
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
