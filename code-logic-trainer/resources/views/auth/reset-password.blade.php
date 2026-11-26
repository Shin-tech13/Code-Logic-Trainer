<x-guest-layout>
    <header class="mb-6 text-center">
        <h1 class="text-[1.625rem] font-bold leading-tight tracking-tight text-ink-text">Choose a new password</h1>
        <p class="mt-2 text-sm leading-6 text-ink-subtle">Create a strong password you have not used before.</p>
    </header>

    <form method="POST" action="{{ route('password.store') }}" class="space-y-4">
        @csrf

        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <div>
            <x-input-label for="email" class="auth-label" :value="__('Code Logic login address')" />
            <x-text-input id="email" class="auth-input mt-2 block w-full" type="email" name="email" :value="old('email', $request->email)" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="auth-input-error mt-2" />
        </div>


        <div>
            <x-input-label for="password" class="auth-label" :value="__('Password')" />
            <x-text-input id="password" class="auth-input mt-2 block w-full" type="password" name="password" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" class="auth-input-error mt-2" />
        </div>


        <div>
            <x-input-label for="password_confirmation" class="auth-label" :value="__('Confirm Password')" />

            <x-text-input id="password_confirmation" class="auth-input mt-2 block w-full"
                                type="password"
                                name="password_confirmation" required autocomplete="new-password" />

            <x-input-error :messages="$errors->get('password_confirmation')" class="auth-input-error mt-2" />
        </div>

        <div class="pt-2">
            <x-primary-button class="auth-primary-button w-full justify-center">
                {{ __('Reset Password') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
