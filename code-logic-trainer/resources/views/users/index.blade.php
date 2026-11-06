<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6 px-6 py-10">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">User directory</h1>
                <p class="mt-1 text-sm text-gray-600">View account roles and who created each account.</p>
            </div>
            @if (Auth::user()->isAdmin())
                <a href="{{ route('admin.users.create') }}"
                    class="inline-flex items-center justify-center rounded-md bg-indigo-600 px-4 py-2 font-semibold text-white hover:bg-indigo-700">
                    Add a user
                </a>
            @endif
        </div>

        @if (session('status'))
            <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-green-800" role="status">
                {{ session('status') }}
            </div>
        @endif

        <form method="GET" action="{{ route('users.index') }}"
            class="grid grid-cols-1 gap-4 rounded-lg bg-white p-5 shadow sm:grid-cols-3 sm:items-end">
            <div>
                <label for="role" class="block text-sm font-medium text-gray-700">Role</label>
                <select id="role" name="role" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                    <option value="">All roles</option>
                    @foreach (['admin' => 'Admin', 'professor' => 'Professor', 'student' => 'Student'] as $value => $label)
                        <option value="{{ $value }}" @selected(request('role') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="created_by" class="block text-sm font-medium text-gray-700">Created by</label>
                <select id="created_by" name="created_by" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                    <option value="">Anyone</option>
                    <option value="system" @selected(request('created_by') === 'system')>System / public registration</option>
                    @foreach ($creators as $creator)
                        <option value="{{ $creator->id }}" @selected((string) request('created_by') === (string) $creator->id)>
                            {{ $creator->name }} ({{ ucfirst($creator->role) }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="flex gap-2">
                <button type="submit"
                    class="rounded-md bg-indigo-600 px-4 py-2 font-semibold text-white hover:bg-indigo-700">
                    Filter
                </button>
                <a href="{{ route('users.index') }}"
                    class="rounded-md border border-gray-300 px-4 py-2 font-semibold text-gray-700 hover:bg-gray-50">
                    Clear
                </a>
            </div>
        </form>

        <div class="overflow-hidden rounded-lg bg-white shadow">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Name</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Email</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Role</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Created by</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Created</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($users as $user)
                            <tr>
                                <td class="whitespace-nowrap px-6 py-4 font-medium text-gray-900">{{ $user->name }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-600">{{ $user->email }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-700">{{ ucfirst($user->role) }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">
                                    @if ($user->createdBy)
                                        <span>{{ $user->createdBy->name }}</span>
                                        <span class="block text-xs text-gray-500">{{ $user->createdBy->email }} · {{ ucfirst($user->createdBy->role) }}</span>
                                    @else
                                        <span class="text-gray-500">System / public registration</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">{{ $user->created_at->format('M j, Y') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-10 text-center text-sm text-gray-500">No accounts match these filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-gray-100 px-6 py-4">
                {{ $users->links() }}
            </div>
        </div>
    </div>
</x-app-layout>
