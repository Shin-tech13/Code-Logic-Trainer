<x-guest-layout>
    <header class="mb-6 text-center">
        <h1 class="text-[1.625rem] font-bold leading-tight tracking-tight text-ink-text">Confirm access</h1>
    </header>

    <div class="mb-5 text-sm leading-6 text-ink-subtle">
        {{ __('This is a secure area of the application. Please confirm your password before continuing.') }}
    </div>

    <form method="POST" action="{{ route('password.confirm') }}" class="space-y-4">
        @csrf

        <div>
            <x-input-label for="password" class="auth-label" :value="__('Password')" />

            <x-text-input id="password" class="auth-input mt-2 block w-full"
                            type="password"
                            name="password"
                            required autocomplete="current-password" />

            <x-input-error :messages="$errors->get('password')" class="auth-input-error mt-2" />
        </div>

        <div class="pt-1">
            <x-primary-button class="auth-primary-button w-full justify-center">
                {{ __('Confirm') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
