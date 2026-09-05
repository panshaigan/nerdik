<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark" data-theme="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <x-seo.head-meta :metadata="$seo ?? \App\Support\Seo\Seo::forWelcome()" />

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=cinzel:400,500,600,700|figtree:400,600&display=swap" rel="stylesheet" />

        <x-theme-script />

        <x-echo-config />

        @vite(['resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-transparent font-sans text-base-content antialiased">
        <x-app-shell-background />

        <x-environment-indicator />

        <div class="relative z-10 mx-auto flex min-h-screen w-full max-w-7xl flex-col px-6 py-8 lg:px-8">

            <header class="flex items-center justify-between gap-4 pb-6">
                <div class="flex items-center">
                    <x-brand-logo size="sm" class="h-10 w-auto" />
                    <div>
                        <p class="font-display text-lg font-semibold">{{ config('app.name', 'nerdik') }}</p>
                        <!--p class="font-display text-sm opacity-70">{{ __('ui.welcome.tagline') }}</p-->
                    </div>
                </div>
                @if (Route::has('login'))
                    <livewire:welcome.navigation />
                @endif
            </header>

            <main class="flex-1 py-8">
                @include('welcome.hero', [
                    'stats' => $stats,
                    'heroImage' => $heroImage ?? null,
                ])

                @include('welcome.problem-solution')
                @include('welcome.closest-listings', [
                    'upcomingListings' => $upcomingListings ?? collect(),
                ])
                @include('welcome.features')
                @include('welcome.how-it-works')

                @include('welcome.final-cta')
            </main>

            <footer class="mt-10 border-t border-white/10 bg-black/35 px-4 py-5 text-sm opacity-70 backdrop-blur-xs sm:px-6 lg:px-8">
                <div class="mx-auto flex max-w-7xl flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <!--p>{{ config('app.name', 'nerdik') }} · {{ __('ui.welcome.footer_tagline') }}</p-->
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1">
                        <a href="{{ route('privacy') }}" class="link link-hover opacity-80">{{ __('ui.footer.privacy') }}</a>
                        <a href="{{ route('terms') }}" class="link link-hover opacity-80">{{ __('ui.footer.terms') }}</a>
                        <a href="{{ route('contact') }}" class="link link-hover opacity-80">{{ __('ui.footer.contact') }}</a>
                    </div>
                </div>
            </footer>
        </div>
    </body>
</html>
