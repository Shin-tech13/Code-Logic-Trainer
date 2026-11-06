<x-app-layout>
    <div class="mx-auto max-w-2xl space-y-6 px-6 py-10">
        <div>
            <a href="{{ route('users.index') }}" class="text-sm font-semibold text-indigo-600 hover:underline">← User directory</a>
            <h1 class="mt-3 text-2xl font-bold text-gray-900">Create an account</h1>
            <p class="mt-1 text-sm text-gray-600">Create a professor or student account. Students created here are authorized to sign in.</p>
        </div>

        <form method="POST" action="{{ route('admin.users.store') }}" class="space-y-5 rounded-lg bg-white p-6 shadow">
            @csrf

            <div>
                <label for="name" class="block text-sm font-medium text-gray-700">Full name</label>
                <input id="name" name="name" type="text" value="{{ old('name') }}" required maxlength="255" autocomplete="name"
                    class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="email" class="block text-sm font-medium text-gray-700">Email address</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required maxlength="255" autocomplete="email"
                    class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="role" class="block text-sm font-medium text-gray-700">Account role</label>
                <select id="role" name="role" required class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                    <option value="">Choose a role</option>
                    <option value="professor" @selected(old('role') === 'professor')>Professor</option>
                    <option value="student" @selected(old('role') === 'student')>Student</option>
                </select>
                @error('role') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-gray-700">Temporary password</label>
                <input id="password" name="password" type="password" required autocomplete="new-password"
                    class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                @error('password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="password_confirmation" class="block text-sm font-medium text-gray-700">Confirm password</label>
                <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"
                    class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
            </div>

            <div class="flex flex-col-reverse gap-3 border-t border-gray-100 pt-5 sm:flex-row sm:justify-end">
                <a href="{{ route('users.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-center font-semibold text-gray-700 hover:bg-gray-50">Cancel</a>
                <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 font-semibold text-white hover:bg-indigo-700">Create account</button>
            </div>
        </form>
    </div>
</x-app-layout>
