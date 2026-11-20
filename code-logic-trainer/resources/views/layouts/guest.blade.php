<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Code Logic Trainer') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <main class="auth-shell relative flex min-h-screen flex-col items-center justify-center overflow-hidden px-4 py-10 sm:px-6">
            <div class="ambient-orb ambient-orb--violet" aria-hidden="true"></div>
            <div class="ambient-orb ambient-orb--indigo" aria-hidden="true"></div>
            <div class="ambient-orb ambient-orb--cyan" aria-hidden="true"></div>

            <div class="relative z-10 mb-6 text-center">
                <a href="/" class="inline-flex flex-col items-center rounded-2xl focus:outline-none focus-visible:ring-2 focus-visible:ring-violet-400 focus-visible:ring-offset-4 focus-visible:ring-offset-[#0D0916]">
                    <span class="mx-auto rounded-2xl bg-white/90 p-3 shadow-[0_0_25px_rgba(139,92,246,0.4)]">
                        <x-application-logo class="h-12 w-auto" />
                    </span>
                    <span class="mt-3 text-xs font-bold uppercase tracking-[0.24em] text-[#E9D5FF]">CODE LOGIC TRAINER</span>
                </a>
            </div>

            <section class="auth-card relative z-10 w-full max-w-md rounded-2xl border border-white/15 bg-[#1A132B]/70 px-6 py-8 shadow-[0_16px_48px_rgba(0,0,0,0.6)] backdrop-blur-xl sm:px-9 sm:py-9 @if ($errors->any()) auth-card--has-errors @endif">
                {{ $slot }}
            </section>

            <p class="relative z-10 mt-6 text-center text-xs text-[#A59EB8]">
                Learn, practice, and strengthen your coding logic.
            </p>
        </main>
    </body>
</html>
