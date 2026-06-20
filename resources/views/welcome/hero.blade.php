@php
    /** @var \App\Services\Welcome\WelcomePlatformStats $stats */
    /** @var \App\Support\Welcome\WelcomeHeroTagImage|null $heroImage */
@endphp

<section class="rounded-3xl border border-base-300 bg-base-100/90 p-8 shadow-xl shadow-primary/10 backdrop-blur-sm md:p-12">
    <div @class([
        'grid gap-10 items-center',
        'md:grid-cols-2' => $heroImage !== null,
    ])>
        <div>
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

        @if ($heroImage !== null)
            <div class="relative mx-auto w-full max-w-md md:max-w-none">
                <div class="overflow-hidden rounded-2xl border border-primary/20 shadow-2xl shadow-primary/20 ring-1 ring-primary/10">
                    <x-media-picture
                        :sources="$heroImage->sources"
                        class="aspect-[4/3] w-full object-cover"
                        loading="eager"
                    />
                </div>
                @if ($heroImage->label !== '')
                    <span class="absolute bottom-4 left-4 rounded-lg bg-base-100/90 px-3 py-1.5 text-sm font-semibold shadow-lg backdrop-blur-sm">
                        {{ $heroImage->label }}
                    </span>
                @endif
            </div>
        @endif
    </div>
</section>
