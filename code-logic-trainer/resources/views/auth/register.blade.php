<x-guest-layout>
    <header class="mb-6 text-center">
        <h1 class="text-[1.625rem] font-bold leading-tight tracking-tight text-ink-text">Create your account</h1>
        <p class="mt-2 text-sm leading-6 text-ink-subtle">Build your coding skills, one challenge at a time.</p>
    </header>

    <form method="POST" action="{{ route('register') }}" class="space-y-4" x-data="{ loading: false }" x-on:submit="loading = true">
        @csrf

        <div>
            <x-input-label for="name" class="auth-label" :value="__('Name')" />
            <x-text-input id="name" class="auth-input mt-2 block w-full" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" placeholder="Your name" />
            <x-input-error :messages="$errors->get('name')" class="auth-input-error mt-2" />
        </div>

        <div>
            <x-input-label for="personal_email" class="auth-label" :value="__('Personal email address')" />
            <x-text-input id="personal_email" class="auth-input mt-2 block w-full" type="email" name="personal_email" :value="old('personal_email')" required autocomplete="email" placeholder="example@codelogic.com" />
            <x-input-error :messages="$errors->get('personal_email')" class="auth-input-error mt-2" />
        </div>

        <div>
            <x-input-label for="year_level" class="auth-label" :value="__('Year level')" />
            <select id="year_level" name="year_level" class="auth-input mt-2" required>
                <option value="" disabled @selected(old('year_level') === null)>Select your year level</option>
                @foreach (['1st Year', '2nd Year', '3rd Year', '4th Year', '5th Year'] as $yearLevel)
                    <option value="{{ $yearLevel }}" @selected(old('year_level') === $yearLevel)>{{ $yearLevel }}</option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('year_level')" class="auth-input-error mt-2" />
        </div>

        <div>
            <x-input-label for="student_id" class="auth-label" :value="__('Student ID')" />
            <x-text-input id="student_id" class="auth-input mt-2 block w-full" type="text" name="student_id" :value="old('student_id')" required autocomplete="off" placeholder="Enter your student ID" />
            <x-input-error :messages="$errors->get('student_id')" class="auth-input-error mt-2" />
        </div>

        <div x-data="{ visible: false }">
            <x-input-label for="password" class="auth-label" :value="__('Password')" />
            <div class="relative mt-2">
                <x-text-input id="password" class="auth-input auth-input--with-toggle block w-full" x-bind:type="visible ? 'text' : 'password'"
                    name="password" required autocomplete="new-password" placeholder="Create a password" />
                <button type="button" class="auth-password-toggle absolute inset-y-0 right-0 inline-flex min-w-11 items-center justify-center rounded-r-[var(--radius-field)] px-3 text-ink-subtle transition-colors duration-quick hover:text-ink-text focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-accent-400" x-on:click="visible = !visible" x-bind:aria-label="visible ? 'Hide password' : 'Show password'" aria-controls="password">
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

        <div x-data="{ visible: false }">
            <x-input-label for="password_confirmation" class="auth-label" :value="__('Confirm Password')" />
            <div class="relative mt-2">
                <x-text-input id="password_confirmation" class="auth-input auth-input--with-toggle block w-full" x-bind:type="visible ? 'text' : 'password'"
                    name="password_confirmation" required autocomplete="new-password" placeholder="Confirm your password" />
                <button type="button" class="auth-password-toggle absolute inset-y-0 right-0 inline-flex min-w-11 items-center justify-center rounded-r-[var(--radius-field)] px-3 text-ink-subtle transition-colors duration-quick hover:text-ink-text focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-accent-400" x-on:click="visible = !visible" x-bind:aria-label="visible ? 'Hide password' : 'Show password'" aria-controls="password_confirmation">
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
            <x-input-error :messages="$errors->get('password_confirmation')" class="auth-input-error mt-2" />
        </div>

        <div class="rounded-[var(--radius-control)] border border-white/10 bg-ink-950/30 p-4">
            <x-user-agreement-disclosure />

            <label for="terms" class="mt-3 flex min-h-11 cursor-pointer items-start gap-3 rounded-[var(--radius-field)] px-1 py-2">
                <input id="terms" type="checkbox" name="terms" value="1" required @checked(old('terms'))
                    class="auth-checkbox mt-1 size-4 shrink-0 accent-accent-500 focus-visible:ring-2 focus-visible:ring-accent-400 focus-visible:ring-offset-2 focus-visible:ring-offset-ink-850"
                    @if ($errors->has('terms')) aria-invalid="true" aria-describedby="terms-error" @endif>
                <span class="text-sm leading-6 text-ink-muted">{{ __('I have read and agree to the User Agreement & Academic Integrity Policy.') }}</span>
            </label>
            <x-input-error id="terms-error" :messages="$errors->get('terms')" class="auth-input-error mt-1" />
        </div>

        <div class="pt-2">
            <x-primary-button class="auth-primary-button w-full justify-center" x-bind:disabled="loading">
                <span x-show="!loading">{{ __('Create Account') }}</span>
                <span x-show="loading" x-cloak class="inline-flex items-center gap-2">
                    <svg class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4Z" />
                    </svg>
                    {{ __('Creating account...') }}
                </span>
            </x-primary-button>
        </div>
    </form>

    <p class="mt-6 border-t border-white/10 pt-5 text-center text-sm leading-6 text-ink-muted">
        {{ __('Already have an account?') }}
        <a class="auth-link font-semibold" href="{{ route('login') }}">{{ __('Log in') }}</a>
    </p>
</x-guest-layout>
