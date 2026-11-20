<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6 px-6 py-10">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">User directory</h1>
                <p class="mt-1 text-sm text-gray-600">Review pending accounts, account roles, and who created each account.</p>
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
            <div>
                <label for="status" class="block text-sm font-medium text-gray-700">Account status</label>
                <select id="status" name="status" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                    <option value="">All statuses</option>
                    @foreach (['pending' => 'Pending', 'approved' => 'Approved', 'suspended' => 'Suspended'] as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
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
                            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Code Logic login</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Role</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Status</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Created by</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Created</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($users as $user)
                            <tr>
                                <td class="whitespace-nowrap px-6 py-4 font-medium text-gray-900">{{ $user->name }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-600">{{ $user->email }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-700">{{ ucfirst($user->role) }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm">
                                    <span class="rounded-full px-2.5 py-1 text-xs font-semibold
                                        {{ $user->account_status === 'approved' ? 'bg-green-100 text-green-800' : ($user->account_status === 'pending' ? 'bg-amber-100 text-amber-800' : 'bg-red-100 text-red-800') }}">
                                        {{ ucfirst($user->account_status) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-700">
                                    @if ($user->createdBy)
                                        <span>{{ $user->createdBy->name }}</span>
                                        <span class="block text-xs text-gray-500">{{ $user->createdBy->email }} · {{ ucfirst($user->createdBy->role) }}</span>
                                    @else
                                        <span class="text-gray-500">System / public registration</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">{{ $user->created_at->format('M j, Y') }}</td>
                                <td class="min-w-64 px-6 py-4 text-sm">
                                    <div class="flex flex-col gap-3">
                                        @if ($user->account_status === 'pending' && ($user->isStudent() || Auth::user()->isAdmin()))
                                            <form method="POST" action="{{ route('users.approve', $user) }}">
                                                @csrf
                                                <button type="submit" class="font-semibold text-green-700 hover:text-green-900">
                                                    Approve account
                                                </button>
                                            </form>
                                        @endif

                                        @if (Auth::user()->isAdmin() && Auth::id() !== $user->id)
                                            <form method="POST" action="{{ route('admin.users.role.update', $user) }}" class="flex items-center gap-2">
                                                @csrf
                                                @method('PATCH')
                                                <label class="sr-only" for="role-{{ $user->id }}">Role for {{ $user->name }}</label>
                                                <select id="role-{{ $user->id }}" name="role" class="rounded-md border-gray-300 text-sm">
                                                    @foreach (['student' => 'Student', 'professor' => 'Professor', 'admin' => 'Admin'] as $value => $label)
                                                        <option value="{{ $value }}" @selected($user->role === $value)>{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                                <button type="submit" class="font-semibold text-indigo-700 hover:text-indigo-900">Save role</button>
                                            </form>

                                            <form method="POST" action="{{ route('admin.users.status.update', $user) }}" class="flex items-center gap-2">
                                                @csrf
                                                @method('PATCH')
                                                <label class="sr-only" for="status-{{ $user->id }}">Status for {{ $user->name }}</label>
                                                <select id="status-{{ $user->id }}" name="status" class="rounded-md border-gray-300 text-sm">
                                                    <option value="approved" @selected($user->account_status === 'approved')>Approved</option>
                                                    <option value="suspended" @selected($user->account_status === 'suspended')>Suspended</option>
                                                </select>
                                                <button type="submit" class="font-semibold text-indigo-700 hover:text-indigo-900">Save status</button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-10 text-center text-sm text-gray-500">No accounts match these filters.</td>
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
