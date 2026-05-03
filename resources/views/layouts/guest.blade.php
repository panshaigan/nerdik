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

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-base-200 font-sans antialiased text-base-content">
        <div class="flex min-h-screen flex-col items-center pt-6 sm:justify-center sm:pt-0">
            <div>
                <a href="/" wire:navigate>
                    <x-brand-logo class="h-20 w-20 fill-current text-base-content/60" />
                </a>
            </div>

            <div class="mt-6 w-full overflow-hidden rounded-lg border border-base-300 bg-base-100 px-6 py-4 shadow sm:max-w-md">
                {{ $slot }}
            </div>
        </div>

        <script>
            function nerdikAuthRegisterRecaptcha(token) {
                nerdikWireSetRecaptchaToken('ui-auth-register-form', token);
            }

            function nerdikAuthForgotRecaptcha(token) {
                nerdikWireSetRecaptchaToken('ui-auth-forgot-form', token);
            }

            function nerdikWireSetRecaptchaToken(formId, token) {
                const form = document.getElementById(formId);
                if (!form || typeof window.Livewire === 'undefined') {
                    return;
                }

                const root = form.closest('[wire\\:id]');
                const id = root ? root.getAttribute('wire:id') : null;
                if (id !== null && id !== '') {
                    window.Livewire.find(id).set('gRecaptchaResponse', token);
                }
            }
        </script>

        @stack('scripts')
    </body>
</html>
