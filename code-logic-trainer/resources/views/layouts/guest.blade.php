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
        <main class="auth-shell relative flex min-h-screen flex-col items-center justify-center overflow-hidden px-4 py-6 sm:px-6 sm:py-10">
            <div class="ambient-orb ambient-orb--violet" aria-hidden="true"></div>
            <div class="ambient-orb ambient-orb--indigo" aria-hidden="true"></div>
            <div class="ambient-orb ambient-orb--plum" aria-hidden="true"></div>

            <div class="relative z-10 mb-5 text-center sm:mb-6">
                <a href="/" class="inline-flex flex-col items-center rounded-[var(--radius-control)] transition-opacity duration-quick hover:opacity-90 focus:outline-none focus-visible:ring-2 focus-visible:ring-accent-400 focus-visible:ring-offset-4 focus-visible:ring-offset-ink-900">
                    <span class="mx-auto rounded-[var(--radius-panel)] bg-ink-text/95 p-2.5 shadow-[0_12px_28px_-16px_rgba(139,92,246,0.8)] sm:p-3">
                        <x-application-logo class="h-10 w-auto sm:h-12" />
                    </span>
                    <span class="mt-3 text-xs font-semibold tracking-wide text-accent-100">Code Logic Trainer</span>
                </a>
            </div>

            <section class="app-glass-panel auth-card relative z-10 w-full max-w-[25rem] px-5 py-6 sm:px-8 sm:py-8 @if ($errors->any()) auth-card--has-errors @endif">
                {{ $slot }}
            </section>

            <p class="relative z-10 mt-5 text-center text-xs leading-5 text-ink-subtle sm:mt-6">
                Learn, practice, and strengthen your coding logic.
            </p>
        </main>
    </body>
</html>
