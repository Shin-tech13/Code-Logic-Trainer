<x-app-layout>
    <div class="auth-shell relative isolate min-h-[calc(100vh-4rem)] overflow-hidden px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
        <div class="ambient-orb ambient-orb--violet" aria-hidden="true"></div>
        <div class="ambient-orb ambient-orb--indigo" aria-hidden="true"></div>
        <div class="ambient-orb ambient-orb--plum" aria-hidden="true"></div>
        <main class="relative z-10 mx-auto max-w-7xl space-y-6">
            <header>
                <p class="text-xs font-semibold tracking-wide text-accent-300">Professor workspace</p>
                <h1 class="mt-2 text-3xl font-bold tracking-tight text-ink-text sm:text-4xl">Managed students</h1>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-ink-subtle">
                    Assign registered students or reserve a class place for a whitelisted email before registration.
                </p>
            </header>

            @if (session('status'))
                <div class="rounded-[var(--radius-control)] border border-emerald-300/20 bg-emerald-400/10 px-4 py-3 text-sm leading-5 text-emerald-100" role="status">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-[var(--radius-control)] border border-rose-300/25 bg-rose-400/10 px-4 py-3 text-sm leading-5 text-rose-100" role="alert">
                    <ul class="list-inside list-disc space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <section class="app-glass-panel p-5 sm:p-6">
                <h2 class="text-lg font-semibold tracking-tight text-ink-text">Create a class</h2>
                <p class="mt-1 text-sm text-ink-subtle">A student needs an assigned class before you can approve their account.</p>
                <form method="POST" action="{{ route('professor.classrooms.store') }}" class="mt-4 grid gap-4 sm:grid-cols-[1fr_2fr_auto] sm:items-end">
                    @csrf
                    <div>
                        <label for="class-title" class="auth-label">Class name</label>
                        <input id="class-title" name="title" type="text" value="{{ old('title') }}" required maxlength="255" class="auth-input mt-2" placeholder="BSIT 2A">
                    </div>
                    <div>
                        <label for="class-description" class="auth-label">Description <span class="font-normal text-ink-subtle">(optional)</span></label>
                        <input id="class-description" name="description" type="text" value="{{ old('description') }}" maxlength="1000" class="auth-input mt-2" placeholder="Programming fundamentals">
                    </div>
                    <button type="submit" class="auth-primary-button min-h-12 px-5 text-sm font-semibold">Create class</button>
                </form>
                @if ($classrooms->isNotEmpty())
                    <p class="mt-4 text-xs leading-5 text-ink-subtle">Your classes: {{ $classrooms->pluck('title')->join(', ') }}</p>
                @endif
            </section>

            <section class="app-glass-panel p-5 sm:p-6" aria-labelledby="assign-student-heading">
                <div>
                    <h2 id="assign-student-heading" class="text-lg font-semibold tracking-tight text-ink-text">Assign a student to a class</h2>
                    <p class="mt-1 text-sm leading-5 text-ink-subtle">
                        Registered students move to the selected class. Whitelisted emails are linked automatically when the student registers.
                    </p>
                </div>
                @if ($classrooms->isEmpty())
                    <p class="mt-4 rounded-[var(--radius-control)] border border-amber-300/20 bg-amber-300/10 px-4 py-3 text-sm text-amber-100">
                        Create a class above before assigning students.
                    </p>
                @else
                    <form method="POST" action="{{ route('professor.classrooms.students.assign') }}" class="mt-4 grid gap-4 sm:grid-cols-[minmax(0,1.4fr)_minmax(12rem,1fr)_auto] sm:items-end">
                        @csrf
                        <div class="min-w-0">
                            <label for="assignment-email" class="auth-label">Student or whitelisted email</label>
                            <select id="assignment-email" name="email" required class="app-action-select mt-2 w-full text-base sm:text-sm">
                                <option value="">Choose a student or email</option>
                                @foreach ($assignableStudents as $option)
                                    <option value="{{ $option['email'] }}" @selected(old('email') === $option['email'])>
                                        {{ $option['name'] }} · {{ $option['email'] }} ({{ $option['context'] }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="assignment-classroom" class="auth-label">Class</label>
                            <select id="assignment-classroom" name="classroom_id" required class="app-action-select mt-2 w-full text-base sm:text-sm">
                                <option value="">Choose a class</option>
                                @foreach ($classrooms as $classroom)
                                    <option value="{{ $classroom->id }}" @selected((string) old('classroom_id') === (string) $classroom->id)>
                                        {{ $classroom->title }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <button type="submit" class="app-primary-button min-h-11 px-5 text-sm">Assign to class</button>
                    </form>
                    @if ($assignableStudents->isEmpty())
                        <p class="mt-3 text-xs leading-5 text-ink-subtle">Add a student to your whitelist or connect a managed student before assigning a class.</p>
                    @endif
                @endif
            </section>

            <section class="app-glass-panel overflow-hidden" aria-label="Students managed by you">
                <div class="border-b border-white/10 px-5 py-4 sm:px-6">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <h2 class="font-semibold text-ink-text">Students and class invitations <span class="text-sm font-normal text-ink-subtle">({{ $students->count() + $classAssignments->count() }})</span></h2>
                        <form method="GET" action="{{ route('professor.students.index') }}" class="flex flex-wrap items-end gap-2">
                            <div>
                                <label for="classroom-filter" class="sr-only">Filter by class</label>
                                <select id="classroom-filter" name="classroom_id" class="app-action-select min-h-11 text-base sm:text-sm">
                                    <option value="">All classes</option>
                                    @foreach ($classrooms as $classroom)
                                        <option value="{{ $classroom->id }}" @selected((string) $selectedClassroomId === (string) $classroom->id)>
                                            {{ $classroom->title }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <button type="submit" class="app-secondary-button min-h-11 px-4 text-sm font-semibold">Filter</button>
                            @if ($selectedClassroomId !== null)
                                <a href="{{ route('professor.students.index') }}" class="inline-flex min-h-11 items-center px-2 text-sm font-semibold text-accent-200 underline decoration-accent-300/40 underline-offset-4 focus-visible:outline focus-visible:outline-2 focus-visible:outline-accent-400">
                                    Clear
                                </a>
                            @endif
                        </form>
                    </div>
                </div>
                <div class="overflow-x-auto" role="region" aria-label="Managed students table. Scroll horizontally to view all columns." tabindex="0">
                    <table class="min-w-[760px] w-full text-left text-sm">
                        <thead class="bg-white/[0.035] text-xs tracking-wide text-ink-muted">
                            <tr>
                                <th scope="col" class="px-5 py-3 sm:px-6">Student</th>
                                <th scope="col" class="px-5 py-3">Student ID</th>
                                <th scope="col" class="px-5 py-3">Year level</th>
                                <th scope="col" class="px-5 py-3">Class</th>
                                <th scope="col" class="px-5 py-3">Status</th>
                                <th scope="col" class="px-5 py-3">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/[0.08]">
                            @if ($students->isEmpty() && $classAssignments->isEmpty())
                                <tr>
                                    <td colspan="6" class="px-6 py-12 text-center">
                                        <p class="font-semibold text-ink-text">No students or class invitations found.</p>
                                        <p class="mt-1 text-sm text-ink-subtle">
                                            @if ($selectedClassroomId !== null)
                                                Try another class filter or clear the filter to see all managed students.
                                            @else
                                                Add a student to a class above, or whitelist an email to get started.
                                            @endif
                                        </p>
                                    </td>
                                </tr>
                            @else
                                @foreach ($students as $student)
                                    <tr class="transition-colors hover:bg-white/[0.035]">
                                        <td class="px-5 py-4 sm:px-6">
                                            <span class="block font-semibold text-ink-text">{{ $student->name }}</span>
                                            <span class="mt-1 block font-mono text-xs text-ink-subtle">{{ $student->email }}</span>
                                        </td>
                                        <td class="px-5 py-4 text-ink-soft">{{ $student->student_id ?: 'Not provided' }}</td>
                                        <td class="px-5 py-4 text-ink-soft">{{ $student->year_level ?: 'Not provided' }}</td>
                                        <td class="px-5 py-4 text-ink-soft">{{ $student->enrolledClassrooms->pluck('title')->join(', ') ?: 'Not assigned' }}</td>
                                        <td class="px-5 py-4">
                                            <span class="app-filter-chip">{{ ucfirst($student->account_status) }}</span>
                                        </td>
                                        <td class="px-5 py-4">
                                            <a href="{{ route('professor.students.edit', $student) }}" class="auth-link font-semibold focus-visible:outline focus-visible:outline-2 focus-visible:outline-accent-400">
                                                Manage details
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                                @foreach ($classAssignments as $assignment)
                                    <tr class="transition-colors hover:bg-white/[0.035]">
                                        <td class="px-5 py-4 sm:px-6">
                                            <span class="block font-semibold text-ink-text">Awaiting registration</span>
                                            <span class="mt-1 block break-all font-mono text-xs text-ink-subtle">{{ $assignment->email }}</span>
                                        </td>
                                        <td class="px-5 py-4 text-ink-subtle">Not provided</td>
                                        <td class="px-5 py-4 text-ink-subtle">Not provided</td>
                                        <td class="px-5 py-4 text-ink-soft">{{ $assignment->classroom->title }}</td>
                                        <td class="px-5 py-4">
                                            <span class="inline-flex min-h-7 items-center rounded-full border border-amber-300/20 bg-amber-300/10 px-2.5 text-xs font-medium text-amber-100">Pre-registration</span>
                                        </td>
                                        <td class="px-5 py-4">
                                            <form method="POST" action="{{ route('professor.classroom-email-assignments.destroy', $assignment) }}" onsubmit="return confirm(@js('Remove '.$assignment->email.' from '.$assignment->classroom->title.'?'));">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="app-action-button min-h-11 rounded-[var(--radius-control)] text-rose-200 hover:bg-rose-300/10 hover:text-white">
                                                    Remove assignment
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            @endif
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>
</x-app-layout>
