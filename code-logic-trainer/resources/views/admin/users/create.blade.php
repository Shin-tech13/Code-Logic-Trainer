<x-app-layout>
    <div class="admin-directory-shell relative isolate min-h-[calc(100vh-4rem)] overflow-hidden px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
        <div class="ambient-orb ambient-orb--violet" aria-hidden="true"></div>
        <div class="ambient-orb ambient-orb--indigo" aria-hidden="true"></div>
        <div class="ambient-orb ambient-orb--plum" aria-hidden="true"></div>

        <main class="relative z-10 mx-auto max-w-2xl space-y-6">
            <header>
                <a href="{{ route('users.index') }}" class="auth-link inline-flex min-h-11 items-center gap-2 rounded-[var(--radius-field)] text-sm font-semibold focus-visible:outline focus-visible:outline-2 focus-visible:outline-accent-400 focus-visible:outline-offset-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m15 18-6-6 6-6" />
                    </svg>
                    User directory
                </a>
                <p class="mt-5 text-xs font-semibold tracking-wide text-accent-300">Administration</p>
                <h1 class="mt-2 text-3xl font-bold tracking-tight text-ink-text">Create an account</h1>
                <p class="mt-2 text-sm leading-6 text-ink-subtle">
                    Create an approved account. The personal email is private and is used only for account notifications and recovery.
                </p>
            </header>

            @if ($errors->any())
                <div class="rounded-[var(--radius-control)] border border-rose-300/25 bg-rose-400/10 px-4 py-3 text-sm leading-5 text-rose-100" role="alert" aria-labelledby="create-account-errors-title">
                    <p id="create-account-errors-title" class="font-semibold">Please review the following fields.</p>
                    <ul class="mt-2 list-inside list-disc space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('admin.users.store') }}" class="app-glass-panel space-y-5 p-5 sm:p-7">
                @csrf

                <div>
                    <label for="name" class="auth-label">Full name</label>
                    <input id="name" name="name" type="text" value="{{ old('name') }}" required maxlength="255" autocomplete="name"
                        aria-describedby="@error('name') name-error @enderror" class="auth-input mt-2" placeholder="Student or staff name">
                    @error('name') <p id="name-error" class="auth-input-error mt-2 text-sm" role="alert">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="personal_email" class="auth-label">Personal email address</label>
                    <p id="personal-email-help" class="mt-1 text-xs leading-5 text-ink-subtle">Private contact address for account notifications and recovery.</p>
                    <input id="personal_email" name="personal_email" type="email" value="{{ old('personal_email') }}" required maxlength="255" autocomplete="email" placeholder="username123@gmail.com"
                        aria-describedby="personal-email-help @error('personal_email') personal-email-error @enderror" class="auth-input mt-2">
                    @error('personal_email') <p id="personal-email-error" class="auth-input-error mt-2 text-sm" role="alert">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="role" class="auth-label">Account role</label>
                    <select id="role" name="role" required class="auth-input mt-2">
                        <option value="">Choose a role</option>
                        <option value="admin" @selected(old('role') === 'admin')>Admin</option>
                        <option value="professor" @selected(old('role') === 'professor')>Professor</option>
                        <option value="student" @selected(old('role') === 'student')>Student</option>
                    </select>
                    @error('role') <p class="auth-input-error mt-2 text-sm" role="alert">{{ $message }}</p> @enderror
                </div>

                <div class="grid gap-5 border-t border-white/10 pt-5 sm:grid-cols-2">
                    <div>
                        <label for="password" class="auth-label">Temporary password</label>
                        <input id="password" name="password" type="password" required autocomplete="new-password" class="auth-input mt-2">
                        @error('password') <p class="auth-input-error mt-2 text-sm" role="alert">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="password_confirmation" class="auth-label">Confirm password</label>
                        <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="auth-input mt-2">
                    </div>
                </div>

                <div class="flex flex-col-reverse gap-3 border-t border-white/10 pt-5 sm:flex-row sm:justify-end">
                    <a href="{{ route('users.index') }}" class="app-secondary-button inline-flex min-h-12 items-center justify-center px-5 text-sm font-semibold">
                        Cancel
                    </a>
                    <button type="submit" class="auth-primary-button inline-flex min-h-12 items-center justify-center px-5 text-sm font-semibold">
                        Create account
                    </button>
                </div>
            </form>
        </main>
    </div>
</x-app-layout>
