<x-guest-layout>
    <header class="mb-7 text-center">
        <h1 class="text-2xl font-bold tracking-tight text-[#F8FAFC]">Welcome back</h1>
        <p class="mt-2 text-sm text-[#A59EB8]">Log in to continue your coding journey.</p>
    </header>

    <x-auth-session-status class="auth-session-status mb-5" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="space-y-5" x-data="{ loading: false }" x-on:submit="loading = true">
        @csrf

        <div>
            <x-input-label for="email" class="auth-label" :value="__('Code Logic login address')" />
            <x-text-input id="email" class="auth-input mt-2 block w-full" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" placeholder="example@codelogic.com" />
            <x-input-error :messages="$errors->get('email')" class="auth-input-error mt-2" />
        </div>

        <div x-data="{ visible: false }">
            <x-input-label for="password" class="auth-label" :value="__('Password')" />
            <div class="relative mt-2">
                <x-text-input id="password" class="auth-input auth-input--with-toggle block w-full" x-bind:type="visible ? 'text' : 'password'"
                    name="password" required autocomplete="current-password" placeholder="Enter your password" />
                <button type="button" class="auth-password-toggle absolute inset-y-0 right-0 inline-flex items-center px-3 text-[#A59EB8] hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-[#8B5CF6]" x-on:click="visible = !visible" x-bind:aria-label="visible ? 'Hide password' : 'Show password'" aria-controls="password">
                    <svg x-show="!visible" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.5-6 9.75-6 9.75 6 9.75 6-3.5 6-9.75 6-9.75-6-9.75-6Z" />
                        <circle cx="12" cy="12" r="2.75" />
                    </svg>
                    <svg x-show="visible" x-cloak xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18M10.6 6.2A10.7 10.7 0 0 1 12 6c6.25 0 9.75 6 9.75 6a17.2 17.2 0 0 1-3.1 3.75M6.2 6.2C3.65 7.85 2.25 12 2.25 12s3.5 6 9.75 6c1.35 0 2.55-.28 3.6-.72" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.88 9.88a3 3 0 0 0 4.24 4.24" />
                    </svg>
                </button>
            </div>
            <x-input-error :messages="$errors->get('password')" class="auth-input-error mt-2" />
        </div>

        <div class="flex items-center">
            <label for="remember_me" class="inline-flex cursor-pointer items-center gap-2.5">
                <input id="remember_me" type="checkbox" class="auth-checkbox" name="remember">
                <span class="text-sm text-[#A59EB8]">{{ __('Remember me') }}</span>
            </label>
        </div>

        <div class="space-y-4 pt-1">
            <x-primary-button class="auth-primary-button w-full justify-center" x-bind:disabled="loading">
                <span x-show="!loading">{{ __('Log In') }}</span>
                <span x-show="loading" x-cloak class="inline-flex items-center gap-2">
                    <svg class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4Z" />
                    </svg>
                    {{ __('Logging in...') }}
                </span>
            </x-primary-button>

            @if (Route::has('password.request'))
                <div class="text-center">
                    <a class="auth-link" href="{{ route('password.request') }}">{{ __('Forgot your password?') }}</a>
                </div>
            @endif
        </div>
    </form>

    @if (Route::has('register'))
        <p class="mt-7 border-t border-white/10 pt-5 text-center text-sm text-[#A59EB8]">
            {{ __('New to Code Logic Trainer?') }}
            <a href="{{ route('register') }}" class="auth-link font-semibold">{{ __('Create an account') }}</a>
        </p>
    @endif
</x-guest-layout>
