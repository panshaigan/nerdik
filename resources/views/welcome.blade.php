<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <x-seo.head-meta :metadata="$seo ?? \App\Support\Seo\Seo::forWelcome()" />

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,600&display=swap" rel="stylesheet" />

        <x-theme-script />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-base-200 font-sans text-base-content antialiased">
        <div class="relative mx-auto flex min-h-screen w-full max-w-7xl flex-col px-6 py-8 lg:px-8">
            <div class="pointer-events-none absolute inset-x-0 top-0 -z-10 h-80 bg-gradient-to-b from-primary/15 via-secondary/10 to-transparent blur-3xl"></div>

            <header class="flex items-center justify-between gap-4 pb-6">
                <div class="flex items-center gap-3">
                    <x-brand-logo class="h-10 w-10" />
                    <div>
                        <p class="text-lg font-semibold">{{ config('app.name', 'Nerdik') }}</p>
                        <p class="text-sm opacity-70">{{ __('ui.welcome.tagline') }}</p>
                    </div>
                </div>
                @if (Route::has('login'))
                    <livewire:welcome.navigation />
                @endif
            </header>

            <main class="flex-1 py-8">
                <section class="rounded-3xl border border-base-300 bg-base-100/90 p-8 shadow-xl shadow-primary/10 backdrop-blur-sm md:p-12">
                    <p class="text-sm font-semibold uppercase tracking-[0.2em] text-primary/80">{{ __('ui.welcome.heading') }}</p>
                    <h1 class="mt-3 max-w-3xl text-4xl font-bold leading-tight md:text-5xl">
                        {{ __('ui.welcome.hero_title') }}
                    </h1>
                    <p class="mt-5 max-w-2xl text-lg opacity-80">
                        {{ __('ui.welcome.hero_description') }}
                    </p>

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
                </section>

                <section class="mt-10">
                    <div class="mb-5 flex items-end justify-between gap-4">
                        <div>
                            <h2 class="text-2xl font-semibold md:text-3xl">{{ __('ui.welcome.closest_heading') }}</h2>
                            <p class="mt-1 text-sm opacity-70">{{ __('ui.welcome.closest_subheading') }}</p>
                        </div>
                        <a href="{{ route('search.index') }}" class="link link-primary text-sm font-medium">
                            {{ __('ui.welcome.open_full_calendar') }}
                        </a>
                    </div>

                    @if (($upcomingListings ?? collect())->isNotEmpty())
                        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                            @foreach ($upcomingListings as $listing)
                                <a
                                    href="{{ $listing->detailsUrl }}"
                                    class="group rounded-2xl border border-base-300 bg-base-100 p-3 shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg"
                                >
                                    <div class="relative aspect-video overflow-hidden rounded-xl">
                                        <x-listing-card-picture :picture="$listing->coverPicture" class="h-full w-full object-cover transition duration-500 group-hover:scale-[1.03]" />
                                        <span class="absolute left-2 top-2 rounded-md bg-base-100/85 px-2 py-1 text-xs font-semibold">
                                            {{ $listing->kindCornerLabel }}
                                        </span>
                                    </div>

                                    <div class="px-1 pb-1 pt-3">
                                        <h3 class="line-clamp-2 text-lg font-semibold leading-snug">{{ $listing->name }}</h3>
                                        @if ($listing->timeSummary !== '')
                                            <p class="mt-2 text-sm opacity-80">{{ $listing->timeSummary }}</p>
                                        @endif
                                        @if ($listing->locationSummary !== '')
                                            <p class="mt-1 text-sm opacity-70">{{ $listing->locationSummary }}</p>
                                        @endif
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    @else
                        <div class="rounded-2xl border border-dashed border-base-300 bg-base-100 p-6 text-center">
                            <p class="text-lg font-medium">{{ __('ui.welcome.empty_title') }}</p>
                            <p class="mt-2 text-sm opacity-70">{{ __('ui.welcome.empty_description') }}</p>
                            <x-button :link="route('search.index')" class="btn-primary mt-4">{{ __('ui.welcome.browse_listings') }}</x-button>
                        </div>
                    @endif
                </section>
            </main>

            <footer class="mt-10 border-t border-base-300 py-5 text-sm opacity-70">
                {{ config('app.name', 'Nerdik') }} · {{ __('ui.welcome.footer_tagline') }}
            </footer>
        </div>
    </body>
</html>
