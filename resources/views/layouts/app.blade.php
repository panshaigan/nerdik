<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <x-theme-script />

        @stack('head')

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-sample5 font-sans antialiased text-base-content">
        <div class="min-h-screen flex flex-col">
            <livewire:layout.navigation />

            <!-- Page Heading -->
            @if (isset($header))
                <header class="border-b border-base-300 bg-base-100/90 shadow-sm backdrop-blur">
                    <div class="max-w-7xl mx-auto py-6 px-4 text-base-content sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endif

            <!-- Page Content -->
            <main class="flex-1">
                {{ $slot }}
            </main>

            <footer class="mt-10 border-t border-base-300 bg-base-100/90">
                <div class="mx-auto flex max-w-7xl flex-col gap-2 px-4 py-5 text-sm sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8">
                    <p class="opacity-70">Copyright {{ date('Y') }} Nerdik. All rights reserved.</p>
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1">
                        <a href="#" class="link link-hover opacity-80">Privacy</a>
                        <a href="#" class="link link-hover opacity-80">Terms</a>
                        <a href="#" class="link link-hover opacity-80">Contact</a>
                    </div>
                </div>
            </footer>
        </div>

        <x-toast />

        @stack('scripts')
    </body>
</html>
