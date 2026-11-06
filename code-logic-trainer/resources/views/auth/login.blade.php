<x-guest-layout>
    <div class="mb-7 text-center">
        <h1 class="text-2xl font-bold tracking-tight text-slate-900">Welcome back</h1>
        <p class="mt-2 text-sm text-slate-500">Log in to continue your coding journey.</p>
    </div>

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="space-y-5">
        @csrf

        <!-- Email Address -->
        <div>
            <x-input-label for="email" class="text-sm font-semibold text-slate-700" :value="__('Email address')" />
            <x-text-input id="email" class="mt-2 block w-full rounded-lg border-slate-300 px-3.5 py-3 text-sm shadow-sm placeholder:text-slate-400 focus:border-indigo-500 focus:ring-indigo-500" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" placeholder="you@example.com" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div>
            <x-input-label for="password" class="text-sm font-semibold text-slate-700" :value="__('Password')" />

            <x-text-input id="password" class="mt-2 block w-full rounded-lg border-slate-300 px-3.5 py-3 text-sm shadow-sm placeholder:text-slate-400 focus:border-indigo-500 focus:ring-indigo-500"
                            type="password"
                            name="password"
                            required autocomplete="current-password" placeholder="Enter your password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Remember Me -->
        <div class="flex items-center">
            <label for="remember_me" class="inline-flex cursor-pointer items-center gap-2.5">
                <input id="remember_me" type="checkbox" class="rounded border-slate-300 text-indigo-600 shadow-sm focus:ring-indigo-500" name="remember">
                <span class="text-sm text-slate-600">{{ __('Remember me') }}</span>
            </label>
        </div>

        <div class="space-y-4 pt-1">
            <x-primary-button class="w-full justify-center rounded-lg border-0 bg-indigo-600 px-4 py-3 text-sm font-semibold normal-case tracking-normal shadow-sm hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-800">
                {{ __('Log in') }}
            </x-primary-button>

            @if (Route::has('password.request'))
                <div class="text-center">
                    <a class="rounded font-medium text-indigo-600 transition hover:text-indigo-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2" href="{{ route('password.request') }}">
                        {{ __('Forgot your password?') }}
                    </a>
                </div>
            @endif
        </div>
    </form>

    @if (Route::has('register'))
        <p class="mt-7 border-t border-slate-100 pt-5 text-center text-sm text-slate-600">
            New to Code Logic Trainer?
            <a href="{{ route('register') }}" class="font-semibold text-indigo-600 hover:text-indigo-800">Create an account</a>
        </p>
    @endif
</x-guest-layout>
