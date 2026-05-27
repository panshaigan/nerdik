<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <x-seo.head-meta :metadata="$seo ?? \App\Support\Seo\Seo::defaults()" />

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <x-theme-script />

        @stack('head')

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body
        class="font-sans antialiased text-base-content"
        data-datetime-minute-step="{{ max(1, (int) config('ui-datetime.minute_step', 5)) }}"
        @auth data-user-id="{{ auth()->id() }}" @endauth
    >
        <div class="min-h-screen flex flex-col">
            <livewire:layout.navigation />

            <div class="bg-gradient flex min-h-0 min-w-0 flex-1 flex-col [&>main]:flex [&>main]:min-h-0 [&>main]:flex-1 [&>main]:flex-col">
                <x-main full-width with-nav>
                    <x-slot:content class="!p-0 flex-1 min-h-0 min-w-0 max-w-7xl mx-auto mb-4 sm:mb-6">
                        {{ $slot }}
                    </x-slot:content>

                    <x-slot:footer class="border-t border-base-300 bg-base-100/90">
                        <div class="mx-auto flex max-w-7xl flex-col gap-2 px-4 py-5 text-sm sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8">
                            <p class="opacity-70">Copyright {{ date('Y') }} Nerdik. All rights reserved.</p>
                            <div class="flex flex-wrap items-center gap-x-4 gap-y-1">
                                <a href="#" class="link link-hover opacity-80">Privacy</a>
                                <a href="#" class="link link-hover opacity-80">Terms</a>
                                <a href="#" class="link link-hover opacity-80">Contact</a>
                            </div>
                        </div>
                    </x-slot:footer>
                </x-main>
            </div>
        </div>

        <x-toast />

        @stack('scripts')
    </body>
</html>
