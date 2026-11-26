<x-app-layout>
    <div class="admin-directory-shell relative isolate min-h-[calc(100vh-4rem)] overflow-hidden px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
        <div class="ambient-orb ambient-orb--violet" aria-hidden="true"></div>
        <div class="ambient-orb ambient-orb--indigo" aria-hidden="true"></div>
        <div class="ambient-orb ambient-orb--plum" aria-hidden="true"></div>

        <div class="relative z-10 mx-auto max-w-7xl space-y-6">
            <header class="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-xs font-semibold tracking-wide text-accent-300">
                        {{ Auth::user()->isAdmin() ? 'Administration' : 'Professor workspace' }}
                    </p>
                    <h1 class="mt-2 text-3xl font-bold tracking-tight text-ink-text sm:text-4xl">User directory</h1>
                    <p class="mt-2 max-w-2xl text-sm leading-6 text-ink-subtle">
                        @if (Auth::user()->isAdmin())
                            Review account requests, manage access, and see who created each account.
                        @else
                            Review student and account information available to your professor role.
                        @endif
                    </p>
                </div>
                @if (Auth::user()->isAdmin())
                    <a href="{{ route('admin.users.create') }}" class="auth-primary-button inline-flex min-h-11 items-center justify-center gap-2 px-5 text-sm font-semibold">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14m-7-7h14" />
                        </svg>
                        Add a user
                    </a>
                @endif
            </header>

            @if (session('status'))
                <div class="rounded-[var(--radius-control)] border border-emerald-300/20 bg-emerald-400/10 px-4 py-3 text-sm leading-5 text-emerald-100" role="status" aria-live="polite">
                    {{ session('status') }}
                </div>
            @endif

            @if (session('error'))
                <div class="rounded-[var(--radius-control)] border border-rose-300/25 bg-rose-400/10 px-4 py-3 text-sm leading-5 text-rose-100" role="alert">
                    {{ session('error') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="rounded-[var(--radius-control)] border border-rose-300/25 bg-rose-400/10 px-4 py-3 text-sm leading-5 text-rose-100" role="alert" aria-labelledby="directory-errors-title">
                    <p id="directory-errors-title" class="font-semibold">We couldn’t update the account.</p>
                    <ul class="mt-2 list-inside list-disc space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @php
                $hasFilters = request()->filled('role') || request()->filled('status') || request()->filled('created_by');
            @endphp

            <section class="app-glass-panel p-5 sm:p-6" aria-labelledby="filters-heading">
                <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 id="filters-heading" class="text-base font-semibold text-ink-text">Filter accounts</h2>
                        <p class="mt-1 text-sm text-ink-subtle">Narrow the directory by role, status, or account creator.</p>
                    </div>
                    @if ($hasFilters)
                        <span class="inline-flex items-center rounded-full border border-accent-300/25 bg-accent-400/10 px-3 py-1 text-xs font-medium text-accent-100">
                            Filters active
                        </span>
                    @endif
                </div>

                <form method="GET" action="{{ route('users.index') }}" class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4 xl:items-end">
                    <div>
                        <label for="role" class="auth-label">Role</label>
                        <select id="role" name="role" class="auth-input mt-2">
                            <option value="">All roles</option>
                            @foreach (['admin' => 'Admin', 'professor' => 'Professor', 'student' => 'Student'] as $value => $label)
                                <option value="{{ $value }}" @selected(request('role') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="created_by" class="auth-label">Created by</label>
                        <select id="created_by" name="created_by" class="auth-input mt-2">
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
                        <label for="status" class="auth-label">Account status</label>
                        <select id="status" name="status" class="auth-input mt-2">
                            <option value="">All statuses</option>
                            @foreach (['pending' => 'Pending', 'approved' => 'Approved', 'suspended' => 'Suspended'] as $value => $label)
                                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button type="submit" class="auth-primary-button inline-flex min-h-12 flex-1 items-center justify-center px-5 text-sm font-semibold">
                            Filter
                        </button>
                        @if ($hasFilters)
                            <a href="{{ route('users.index') }}" class="app-secondary-button inline-flex min-h-12 items-center justify-center px-4 text-sm font-semibold">
                                Clear
                            </a>
                        @endif
                    </div>
                </form>

                @if ($hasFilters)
                    <div class="mt-4 flex flex-wrap gap-2" aria-label="Active filters">
                        @if (request('role'))
                            <span class="app-filter-chip">Role: {{ ucfirst(request('role')) }}</span>
                        @endif
                        @if (request('status'))
                            <span class="app-filter-chip">Status: {{ ucfirst(request('status')) }}</span>
                        @endif
                        @if (request('created_by') === 'system')
                            <span class="app-filter-chip">Created by: System / public registration</span>
                        @elseif (request('created_by'))
                            @php
                                $activeCreator = $creators->firstWhere('id', (int) request('created_by'));
                            @endphp
                            <span class="app-filter-chip">Created by: {{ $activeCreator?->name ?? 'Selected creator' }}</span>
                        @endif
                    </div>
                @endif
            </section>

            <section class="app-glass-panel overflow-hidden" aria-label="User accounts">
                <div class="flex flex-col gap-1 border-b border-white/10 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                    <h2 class="font-semibold text-ink-text">Accounts</h2>
                    <p class="text-sm text-ink-subtle">
                        @if ($users->total() > 0)
                            Showing <span class="font-medium tabular-nums text-accent-200">{{ $users->firstItem() }}–{{ $users->lastItem() }}</span>
                            of <span class="font-medium tabular-nums text-accent-200">{{ $users->total() }}</span> accounts
                        @else
                            No accounts found
                        @endif
                    </p>
                </div>

                <div class="overflow-x-auto focus-visible:outline focus-visible:outline-2 focus-visible:outline-accent-400" role="region" aria-label="User accounts table. Scroll horizontally to view all columns." tabindex="0">
                    <table class="min-w-[1050px] w-full border-collapse text-left">
                        <thead class="bg-white/[0.035]">
                            <tr>
                                <th scope="col" class="px-5 py-4 text-xs font-semibold tracking-wide text-ink-muted sm:px-6">Name</th>
                                <th scope="col" class="px-5 py-4 text-xs font-semibold tracking-wide text-ink-muted">Code Logic login</th>
                                <th scope="col" class="px-5 py-4 text-xs font-semibold tracking-wide text-ink-muted">Role</th>
                                <th scope="col" class="px-5 py-4 text-xs font-semibold tracking-wide text-ink-muted">Status</th>
                                <th scope="col" class="px-5 py-4 text-xs font-semibold tracking-wide text-ink-muted">Created by</th>
                                <th scope="col" class="px-5 py-4 text-xs font-semibold tracking-wide text-ink-muted">Created</th>
                                <th scope="col" class="min-w-72 px-5 py-4 text-xs font-semibold tracking-wide text-ink-muted">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/[0.08]">
                            @forelse ($users as $user)
                                @php
                                    $statusClasses = match ($user->account_status) {
                                        'pending' => 'border-amber-300/25 bg-amber-300/10 text-amber-100',
                                        'approved' => 'border-emerald-300/25 bg-emerald-300/10 text-emerald-100',
                                        'suspended' => 'border-rose-300/25 bg-rose-300/10 text-rose-100',
                                        default => 'border-white/15 bg-white/5 text-ink-soft',
                                    };
                                @endphp
                                <tr class="transition-colors duration-quick hover:bg-white/[0.035]">
                                    <td class="whitespace-nowrap px-5 py-4 sm:px-6">
                                        <span class="font-semibold text-ink-text">{{ $user->name }}</span>
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-4 font-mono text-sm text-ink-soft">{{ $user->email }}</td>
                                    <td class="whitespace-nowrap px-5 py-4 text-sm text-accent-200">{{ ucfirst($user->role) }}</td>
                                    <td class="whitespace-nowrap px-5 py-4">
                                        <span class="inline-flex items-center gap-2 rounded-full border px-2.5 py-1 text-xs font-semibold {{ $statusClasses }}">
                                            <span class="h-1.5 w-1.5 rounded-full bg-current" aria-hidden="true"></span>
                                            {{ ucfirst($user->account_status) }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-4 text-sm text-ink-soft">
                                        @if ($user->createdBy)
                                            <span class="block font-medium text-ink-text">{{ $user->createdBy->name }}</span>
                                            <span class="mt-0.5 block text-xs text-ink-subtle">{{ $user->createdBy->email }} · {{ ucfirst($user->createdBy->role) }}</span>
                                        @else
                                            <span class="text-ink-subtle">System / public registration</span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-4 text-sm tabular-nums text-ink-subtle">{{ $user->created_at->format('M j, Y') }}</td>
                                    <td class="px-5 py-4">
                                        <div class="flex min-w-64 flex-col items-start gap-3">
                                            @if ($user->account_status === 'pending' && ($user->isStudent() || Auth::user()->isAdmin()))
                                                <form method="POST" action="{{ route('users.approve', $user) }}">
                                                    @csrf
                                                    <button type="submit" class="auth-primary-button inline-flex min-h-10 items-center justify-center px-4 py-2 text-sm font-semibold">
                                                        Approve account
                                                    </button>
                                                </form>
                                            @endif

                                            @if (Auth::user()->isAdmin() && Auth::id() !== $user->id)
                                                <div class="w-full space-y-2 border-t border-white/10 pt-3">
                                                    <form method="POST" action="{{ route('admin.users.role.update', $user) }}" class="flex items-center gap-2">
                                                        @csrf
                                                        @method('PATCH')
                                                        <label class="sr-only" for="role-{{ $user->id }}">Role for {{ $user->name }}</label>
                                                        <select id="role-{{ $user->id }}" name="role" class="app-action-select min-w-0 flex-1">
                                                            @foreach (['student' => 'Student', 'professor' => 'Professor', 'admin' => 'Admin'] as $value => $label)
                                                                <option value="{{ $value }}" @selected($user->role === $value)>{{ $label }}</option>
                                                            @endforeach
                                                        </select>
                                                        <button type="submit" class="app-action-button">Save role</button>
                                                    </form>

                                                    <form method="POST" action="{{ route('admin.users.status.update', $user) }}" class="flex items-center gap-2">
                                                        @csrf
                                                        @method('PATCH')
                                                        <label class="sr-only" for="status-{{ $user->id }}">Status for {{ $user->name }}</label>
                                                        <select id="status-{{ $user->id }}" name="status" class="app-action-select min-w-0 flex-1">
                                                            <option value="approved" @selected($user->account_status === 'approved')>Approved</option>
                                                            <option value="suspended" @selected($user->account_status === 'suspended')>Suspended</option>
                                                        </select>
                                                        <button type="submit" class="app-action-button">Save status</button>
                                                    </form>
                                                </div>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-6 py-14 text-center">
                                        <p class="font-semibold text-ink-text">No accounts match these filters.</p>
                                        <p class="mt-1 text-sm text-ink-subtle">Try changing or resetting your filters.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($users->hasPages())
                    <div class="border-t border-white/10 px-5 py-4 sm:px-6">
                        {{ $users->links() }}
                    </div>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>
