<x-guest-layout>
    <header class="mb-6 text-center">
        <h1 class="text-2xl font-bold tracking-tight text-[#F8FAFC]">Reset your password</h1>
        <p class="mt-2 text-sm text-[#A59EB8]">We’ll send a secure reset link to your email.</p>
    </header>

    <p class="mb-6 text-center text-sm leading-6 text-[#A59EB8]">
        {{ __('Forgot your password? No problem. Just let us know your email address and we will email you a password reset link that will allow you to choose a new one.') }}
    </p>

    <x-auth-session-status class="mb-4 text-sm text-violet-200" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}" class="space-y-5" x-data="{ loading: false }" x-on:submit="loading = true">
        @csrf

        <div>
            <x-input-label for="email" class="text-sm font-semibold text-[#F8FAFC]" :value="__('Email')" />
            <x-text-input id="email" class="auth-input mt-2 block w-full" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" placeholder="you@example.com" />
            <x-input-error :messages="$errors->get('email')" class="mt-2 text-sm text-rose-300" />
        </div>

        <x-primary-button class="auth-primary-button w-full justify-center" x-bind:disabled="loading">
            <span x-show="!loading">{{ __('Email Password Reset Link') }}</span>
            <span x-show="loading" x-cloak class="inline-flex items-center gap-2">
                <svg class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4Z" />
                </svg>
                {{ __('Sending link...') }}
            </span>
        </x-primary-button>
    </form>

    <p class="mt-7 border-t border-white/10 pt-5 text-center text-sm">
        <a class="auth-link font-semibold" href="{{ route('login') }}">{{ __('Back to login') }}</a>
    </p>
</x-guest-layout>
