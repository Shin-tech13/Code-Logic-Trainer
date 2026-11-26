<x-guest-layout>
    <header class="mb-6 text-center">
        <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full border border-accent-300/25 bg-accent-400/10 text-accent-200" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="m5 12.5 4.5 4.5L19 7" />
            </svg>
        </div>
        <h1 class="text-[1.625rem] font-bold leading-tight tracking-tight text-ink-text">Account request received</h1>
        <p class="mt-2 text-sm leading-6 text-ink-subtle">Your account is pending approval.</p>
    </header>

    <div class="space-y-5 text-sm leading-6 text-ink-soft">
        <section aria-labelledby="login-address-label" x-data="{ copied: false, copyError: false, async copyLoginAddress() { try { await navigator.clipboard.writeText(this.$refs.loginAddress.value); this.copied = true; this.copyError = false; } catch { this.$refs.loginAddress.focus(); this.$refs.loginAddress.select(); this.copyError = true; } } }">
            <label id="login-address-label" for="registration-login-address" class="auth-label">
                {{ __('Your Code Logic login address') }}
            </label>
            <div class="mt-2 flex min-w-0 flex-col gap-2 sm:flex-row">
                <input
                    id="registration-login-address"
                    x-ref="loginAddress"
                    type="text"
                    value="{{ $loginAddress }}"
                    readonly
                    aria-describedby="copy-status"
                    class="auth-input min-w-0 flex-1 font-mono"
                >
                <button
                    type="button"
                    x-on:click="copyLoginAddress()"
                    class="auth-primary-button inline-flex shrink-0 items-center justify-center gap-2 px-4 py-3"
                    aria-label="Copy Code Logic login address"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <rect width="14" height="14" x="8" y="8" rx="2" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16 8V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h3" />
                    </svg>
                    <span x-text="copied ? 'Copied' : 'Copy'">Copy</span>
                </button>
                <p id="copy-status" class="sr-only" aria-live="polite" x-text="copied ? 'Login address copied.' : (copyError ? 'Copy is unavailable. The address is selected; copy it manually.' : '')"></p>
            </div>
            <p class="mt-2 text-xs leading-5 text-ink-subtle">
                {{ __('Use this address when you log in after approval.') }}
            </p>
        </section>

        <div class="rounded-[var(--radius-control)] border border-white/10 bg-white/[0.03] px-4 py-3">
            <p>{{ __('Check the personal email address you provided for a confirmation message.') }}</p>
            <p class="mt-2">{{ __('Approval may take about one day. If you need access sooner, contact your professor.') }}</p>
        </div>
    </div>

    <p class="mt-6 border-t border-white/10 pt-5 text-center text-sm leading-6">
        <a class="auth-link font-semibold" href="{{ route('login') }}">{{ __('Back to login') }}</a>
    </p>
</x-guest-layout>
