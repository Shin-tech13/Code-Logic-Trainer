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

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-900 antialiased">
        <div class="relative flex min-h-screen flex-col items-center justify-center overflow-hidden bg-gradient-to-br from-slate-50 via-white to-indigo-50 px-4 py-10 sm:px-6">
            <div class="pointer-events-none absolute -left-24 -top-24 h-72 w-72 rounded-full bg-indigo-100/60 blur-3xl"></div>
            <div class="pointer-events-none absolute -bottom-28 -right-20 h-80 w-80 rounded-full bg-violet-100/60 blur-3xl"></div>

            <div class="relative z-10 mb-6 text-center">
                <a href="/">
                    <x-application-logo class="mx-auto h-14 w-14 fill-current text-indigo-600 transition hover:text-indigo-700" />
                </a>
                <p class="mt-3 text-xs font-bold uppercase tracking-[0.2em] text-indigo-700">Code Logic Trainer</p>
            </div>

            <div class="relative z-10 w-full max-w-md overflow-hidden rounded-2xl border border-white/80 bg-white/95 px-6 py-8 shadow-xl shadow-slate-200/70 backdrop-blur sm:px-9 sm:py-9">
                {{ $slot }}
            </div>

            <p class="relative z-10 mt-6 text-center text-xs text-slate-500">
                Learn, practice, and strengthen your coding logic.
            </p>
        </div>
    </body>
</html>
