<x-app-layout>
    <div class="auth-shell relative isolate min-h-[calc(100vh-4rem)] overflow-hidden px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
        <div class="ambient-orb ambient-orb--violet" aria-hidden="true"></div>
        <div class="ambient-orb ambient-orb--indigo" aria-hidden="true"></div>
        <div class="ambient-orb ambient-orb--plum" aria-hidden="true"></div>
        <main class="relative z-10 mx-auto max-w-7xl space-y-6">
            <header>
                <p class="text-xs font-semibold tracking-wide text-accent-300">Account</p>
                <h1 class="mt-2 text-3xl font-bold tracking-tight text-ink-text">{{ __('Profile and settings') }}</h1>
                <p class="mt-2 text-sm leading-6 text-ink-subtle">Manage your account information, password, and account security.</p>
            </header>
            <div class="app-glass-panel p-5 sm:p-8">
                <div class="max-w-2xl">
                    @include('profile.partials.update-profile-information-form')
                </div>
            </div>

            <div class="app-glass-panel p-5 sm:p-8">
                <div class="max-w-2xl">
                    @include('profile.partials.update-password-form')
                </div>
            </div>

            <div class="app-glass-panel p-5 sm:p-8">
                <div class="max-w-2xl">
                    @include('profile.partials.delete-user-form')
                </div>
            </div>
        </main>
    </div>
</x-app-layout>
