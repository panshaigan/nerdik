<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name', 'Nerdik') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,600&display=swap" rel="stylesheet" />

        <x-theme-script />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-base-200 font-sans text-base-content antialiased">
        <div class="mx-auto flex min-h-screen w-full max-w-7xl flex-col px-6 py-8 lg:px-8">
            <header class="flex items-center justify-between gap-4 border-b border-base-300 pb-6">
                <div class="flex items-center gap-3">
                    <x-brand-logo class="h-10 w-10" />
                    <div>
                        <p class="text-lg font-semibold">{{ config('app.name', 'Nerdik') }}</p>
                        <p class="text-sm opacity-70">{{ __('Convention and activity management') }}</p>
                    </div>
                </div>
                @if (Route::has('login'))
                    <livewire:welcome.navigation />
                @endif
            </header>

            <main class="grid flex-1 items-start gap-6 py-8 lg:grid-cols-3">
                <section class="rounded-xl border border-base-300 bg-base-100 p-6 shadow-sm lg:col-span-2">
                    <h1 class="text-3xl font-bold leading-tight">
                        {{ __('Manage events, slots and proposals in one place') }}
                    </h1>
                    <p class="mt-4 max-w-2xl text-base opacity-80">
                        {{ __('Nerdik helps organizers and hosts coordinate activities for upcoming events. Build schedules faster, collect activity proposals, and keep participants informed.') }}
                    </p>

                    <div class="mt-6 flex flex-wrap gap-3">
                        @auth
                            <x-button :link="route('dashboard')" class="btn-primary">{{ __('Open dashboard') }}</x-button>
                        @else
                            <x-button :link="route('login')" class="btn-primary">{{ __('Get started') }}</x-button>
                            @if (Route::has('register'))
                                <x-button :link="route('register')" class="btn-outline">{{ __('Create account') }}</x-button>
                            @endif
                        @endauth
                    </div>
                </section>

                <section class="space-y-4">
                    <article class="rounded-xl border border-base-300 bg-base-100 p-5 shadow-sm">
                        <h2 class="text-lg font-semibold">{{ __('Event-first planning') }}</h2>
                        <p class="mt-2 text-sm opacity-80">
                            {{ __('Create events and slots, then review activity proposals directly from the event page.') }}
                        </p>
                    </article>
                    <article class="rounded-xl border border-base-300 bg-base-100 p-5 shadow-sm">
                        <h2 class="text-lg font-semibold">{{ __('Structured forms') }}</h2>
                        <p class="mt-2 text-sm opacity-80">
                            {{ __('Mary + Daisy based forms keep data entry consistent across activities, places, tags and organizations.') }}
                        </p>
                    </article>
                    <article class="rounded-xl border border-base-300 bg-base-100 p-5 shadow-sm">
                        <h2 class="text-lg font-semibold">{{ __('Theme-aware interface') }}</h2>
                        <p class="mt-2 text-sm opacity-80">
                            {{ __('Semantic design tokens ensure light and dark themes stay coherent without manual color tweaking.') }}
                        </p>
                    </article>
                </section>
            </main>

            <footer class="border-t border-base-300 py-5 text-sm opacity-70">
                {{ config('app.name', 'Nerdik') }} · Laravel v{{ Illuminate\Foundation\Application::VERSION }} (PHP v{{ PHP_VERSION }})
            </footer>
        </div>
    </body>
</html>
