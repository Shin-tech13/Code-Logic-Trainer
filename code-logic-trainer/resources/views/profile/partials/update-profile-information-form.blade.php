<section>
    <header>
        <h2 class="text-lg font-semibold tracking-tight text-ink-text">
            {{ __('Profile Information') }}
        </h2>

        <p class="mt-1 text-sm leading-6 text-ink-subtle">
            {{ __("Your recovery email is private and is used for account notifications and password recovery.") }}
        </p>
    </header>

    <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('patch')

        <div>
            <x-input-label for="name" class="auth-label" :value="__('Name')" />
            <x-text-input id="name" name="name" type="text" class="auth-input mt-2 block w-full" :value="old('name', $user->name)" required autofocus autocomplete="name" />
            <x-input-error class="mt-2" :messages="$errors->get('name')" />
        </div>

        <div>
            <x-input-label for="login_address" class="auth-label" :value="__('Code Logic login address')" />
            <p id="login-address-help" class="mt-1 text-xs leading-5 text-ink-subtle">{{ __('This account identifier cannot be changed.') }}</p>
            <x-text-input id="login_address" type="email" class="auth-input app-readonly-input mt-2 block w-full" :value="$user->email" readonly aria-describedby="login-address-help" />
        </div>

        <div>
            <x-input-label for="personal_email" class="auth-label" :value="__('Private recovery email')" />
            <x-text-input id="personal_email" name="personal_email" type="email" class="auth-input mt-2 block w-full" :value="old('personal_email', $user->personal_email)" required autocomplete="email" />
            <x-input-error class="mt-2" :messages="$errors->get('personal_email')" />
        </div>

        <div>
            <x-input-label for="age" class="auth-label" :value="__('Age (optional)')" />
            <x-text-input id="age" name="age" type="number" min="1" max="120" class="auth-input mt-2 block w-full" :value="old('age', $user->age)" />
            <x-input-error class="mt-2" :messages="$errors->get('age')" />
        </div>

        @if ($user->isStudent())
            <div class="grid gap-4 border-t border-white/10 pt-5 sm:grid-cols-2">
                <div>
                    <x-input-label for="student_id_display" class="auth-label" :value="__('Student ID')" />
                    <x-text-input id="student_id_display" type="text" class="auth-input app-readonly-input mt-2 block w-full" :value="$user->student_id ?: __('Not provided')" readonly />
                </div>
                <div>
                    <x-input-label for="year_level_display" class="auth-label" :value="__('Year level')" />
                    <x-text-input id="year_level_display" type="text" class="auth-input app-readonly-input mt-2 block w-full" :value="$user->year_level ?: __('Not provided')" readonly />
                </div>
                <div class="sm:col-span-2">
                    <p class="auth-label">{{ __('Class') }}</p>
                    <p class="mt-2 rounded-[var(--radius-control)] border border-white/10 bg-ink-950/50 px-4 py-3 text-sm leading-5 text-ink-soft">
                        {{ $classrooms->isNotEmpty() ? $classrooms->pluck('title')->join(', ') : __('Not assigned') }}
                    </p>
                </div>
            </div>
        @endif

        <div class="flex min-h-11 flex-wrap items-center gap-4">
            <x-primary-button class="auth-primary-button min-h-11">{{ __('Save profile') }}</x-primary-button>

            @if (session('status') === 'profile-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-sm text-emerald-200"
                >{{ __('Saved.') }}</p>
            @endif
        </div>
    </form>
</section>
