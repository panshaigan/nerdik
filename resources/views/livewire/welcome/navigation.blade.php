<nav class="-mx-3 flex flex-1 justify-end gap-2">
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
