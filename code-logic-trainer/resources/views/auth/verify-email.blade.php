<x-guest-layout>
    <header class="mb-5 text-center">
        <h1 class="text-[1.625rem] font-bold leading-tight tracking-tight text-ink-text">Verify your email</h1>
    </header>

    <div class="text-sm leading-6 text-ink-muted">
        {{ __('Thanks for signing up! Before getting started, could you verify your email address by clicking on the link we just emailed to you? If you didn\'t receive the email, we will gladly send you another.') }}
    </div>

    @if (session('status') == 'verification-link-sent')
        <div class="mt-4 rounded-[var(--radius-control)] border border-emerald-400/20 bg-emerald-400/10 px-4 py-3 text-sm font-medium leading-5 text-emerald-200" role="status">
            {{ __('A new verification link has been sent to the email address you provided during registration.') }}
        </div>
    @endif

    <div class="mt-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf

            <div>
                <x-primary-button class="auth-primary-button w-full justify-center sm:w-auto">
                    {{ __('Resend Verification Email') }}
                </x-primary-button>
            </div>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf

            <button type="submit" class="auth-link w-full min-h-11 rounded-[var(--radius-control)] text-sm font-semibold sm:w-auto">
                {{ __('Log Out') }}
            </button>
        </form>
    </div>
</x-guest-layout>
