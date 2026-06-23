@php
    /** @var \App\Services\Welcome\WelcomePlatformStats $stats */
    /** @var \App\Support\Welcome\WelcomeHeroTagImage|null $heroImage */
@endphp

<section @class([
    'relative overflow-hidden rounded-3xl border border-base-300 shadow-xl shadow-primary/10',
    'bg-base-100/90 backdrop-blur-sm' => $heroImage === null,
])>
    @if ($heroImage !== null)
        <div class="pointer-events-none absolute inset-0" aria-hidden="true">
            <x-media-picture
                :sources="$heroImage->sources"
                class="h-full w-full object-cover"
                loading="eager"
            />
            <div class="absolute inset-0 bg-gradient-to-r from-base-100/95 via-base-100/85 to-base-100/55"></div>
            <div class="absolute inset-0 bg-black/55 backdrop-blur-[1px]"></div>
        </div>
    @endif

    <div class="relative z-10 p-8 md:p-12">
        <p class="text-sm font-semibold uppercase tracking-[0.2em] text-primary/80">{{ __('ui.welcome.heading') }}</p>
        <h1 class="mt-3 max-w-3xl text-4xl font-bold leading-tight md:text-5xl">
            {{ __('ui.welcome.hero_title') }}
        </h1>
        <p class="mt-5 max-w-2xl text-lg opacity-80">
            {{ __('ui.welcome.hero_description') }}
        </p>

        @include('welcome.stats', ['stats' => $stats])

        <div class="mt-8 flex flex-wrap gap-3">
            @auth
                <x-button :link="route('dashboard')" class="btn-primary">{{ __('ui.welcome.continue_journey') }}</x-button>
                <x-button :link="route('search.index')" class="btn-outline">{{ __('ui.welcome.browse_everything') }}</x-button>
            @else
                @if (Route::has('register'))
                    <x-button :link="route('register')" class="btn-primary">{{ __('ui.welcome.start_exploring') }}</x-button>
                @endif
                <x-button :link="route('search.index')" class="btn-outline">{{ __('ui.welcome.see_upcoming_events') }}</x-button>
                @if (Route::has('login'))
                    <x-button :link="route('login')" class="btn-ghost">{{ __('ui.welcome.already_have_account') }}</x-button>
                @endif
            @endauth
        </div>
    </div>
</section>
