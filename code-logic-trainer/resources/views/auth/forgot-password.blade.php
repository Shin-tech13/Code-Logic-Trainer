<x-guest-layout>
    <header class="mb-7 text-center">
        <h1 class="text-2xl font-bold tracking-tight text-[#F8FAFC]">Reset your password</h1>
        <p class="mt-2 text-sm text-[#A59EB8]">Enter your email and we’ll send you a secure reset link.</p>
    </header>

    <x-auth-session-status class="auth-session-status mb-5" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}" class="space-y-5" x-data="{ loading: false }" x-on:submit="loading = true">
        @csrf

        <div>
            <x-input-label for="email" class="auth-label" :value="__('Email')" />
            <x-text-input id="email" class="auth-input mt-2 block w-full" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" placeholder="example@codelogic.com" />
            <x-input-error :messages="$errors->get('email')" class="auth-input-error mt-2" />
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

    <p class="mt-6 border-t border-white/10 pt-4 text-center text-sm">
        <a class="auth-link font-semibold" href="{{ route('login') }}">{{ __('Back to login') }}</a>
    </p>
</x-guest-layout>
