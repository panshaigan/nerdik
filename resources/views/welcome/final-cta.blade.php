<section class="mt-16 rounded-3xl border border-primary/25 bg-base-100/90 p-8 text-center shadow-xl shadow-primary/10 md:p-12">
    <h2 class="text-2xl font-semibold md:text-3xl">{{ __('ui.welcome.final_cta_heading') }}</h2>
    <p class="mx-auto mt-3 max-w-xl text-sm opacity-75">{{ __('ui.welcome.final_cta_description') }}</p>

    <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
        @auth
            <x-button :link="route('dashboard')" class="btn-primary">{{ __('ui.welcome.final_cta_primary_auth') }}</x-button>
        @else
            @if (Route::has('register'))
                <x-button :link="route('register')" class="btn-primary">{{ __('ui.welcome.final_cta_primary_guest') }}</x-button>
            @endif
        @endauth
        <x-button :link="route('search.index')" class="btn-outline">{{ __('ui.welcome.final_cta_secondary') }}</x-button>
    </div>
</section>
