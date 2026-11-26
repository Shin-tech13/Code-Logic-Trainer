<x-app-layout>
    <div class="auth-shell relative isolate min-h-[calc(100vh-4rem)] overflow-hidden px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
        <div class="ambient-orb ambient-orb--violet" aria-hidden="true"></div>
        <div class="ambient-orb ambient-orb--indigo" aria-hidden="true"></div>
        <div class="ambient-orb ambient-orb--plum" aria-hidden="true"></div>
        <main class="relative z-10 mx-auto max-w-3xl space-y-6">
            <a href="{{ route('professor.students.index') }}" class="auth-link inline-flex min-h-11 items-center rounded-[var(--radius-field)] text-sm font-semibold focus-visible:outline focus-visible:outline-2 focus-visible:outline-accent-400">← Back to managed students</a>
            <section class="app-glass-panel p-5 sm:p-8">
                <header class="mb-6">
                    <p class="text-xs font-semibold tracking-wide text-accent-300">Student details</p>
                    <h1 class="mt-2 text-2xl font-bold tracking-tight text-ink-text">{{ $student->name }}</h1>
                    <p class="mt-2 text-sm leading-6 text-ink-subtle">Update the student's class and profile information. Recovery email is visible only to you and the student.</p>
                </header>

                @if (session('status'))
                    <div class="mb-5 rounded-[var(--radius-control)] border border-emerald-300/20 bg-emerald-400/10 px-4 py-3 text-sm leading-5 text-emerald-100" role="status">{{ session('status') }}</div>
                @endif
                @if ($errors->any())
                    <div class="mb-5 rounded-[var(--radius-control)] border border-rose-300/25 bg-rose-400/10 px-4 py-3 text-sm leading-5 text-rose-100" role="alert">
                        <ul class="list-inside list-disc space-y-1">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('professor.students.update', $student) }}" class="space-y-5">
                    @csrf
                    @method('PATCH')
                    <div>
                        <label for="name" class="auth-label">Name</label>
                        <input id="name" name="name" type="text" value="{{ old('name', $student->name) }}" required maxlength="255" class="auth-input mt-2">
                    </div>
                    <div>
                        <label for="personal_email" class="auth-label">Private recovery email</label>
                        <input id="personal_email" name="personal_email" type="email" value="{{ old('personal_email', $student->personal_email) }}" required maxlength="255" class="auth-input mt-2">
                    </div>
                    <div class="grid gap-5 sm:grid-cols-2">
                        <div>
                            <label for="age" class="auth-label">Age <span class="font-normal text-ink-subtle">(optional)</span></label>
                            <input id="age" name="age" type="number" min="1" max="120" value="{{ old('age', $student->age) }}" class="auth-input mt-2">
                        </div>
                        <div>
                            <label for="year_level" class="auth-label">Year level</label>
                            <select id="year_level" name="year_level" required class="auth-input mt-2">
                                <option value="">Select year level</option>
                                @foreach (['1st Year', '2nd Year', '3rd Year', '4th Year', '5th Year'] as $yearLevel)
                                    <option value="{{ $yearLevel }}" @selected(old('year_level', $student->year_level) === $yearLevel)>{{ $yearLevel }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div>
                        <label for="student_id" class="auth-label">Student ID</label>
                        <input id="student_id" name="student_id" type="text" value="{{ old('student_id', $student->student_id) }}" required maxlength="100" class="auth-input mt-2">
                    </div>
                    <div>
                        <label for="classroom_id" class="auth-label">Class</label>
                        <select id="classroom_id" name="classroom_id" class="auth-input mt-2">
                            <option value="">Not assigned</option>
                            @foreach ($classrooms as $classroom)
                                <option value="{{ $classroom->id }}" @selected((string) old('classroom_id', $assignedClassroomIds[0] ?? '') === (string) $classroom->id)>
                                    {{ $classroom->title }} · {{ $classroom->class_code }}
                                </option>
                            @endforeach
                        </select>
                        @if ($classrooms->isEmpty())
                            <p class="mt-2 text-xs text-amber-100">Create a class on the Managed students page before approving this student's account.</p>
                        @else
                            <p class="mt-2 text-xs leading-5 text-ink-subtle">A class assignment is required before you can approve a pending account.</p>
                        @endif
                    </div>
                    <div class="flex flex-wrap items-center gap-3 border-t border-white/10 pt-5">
                        <button type="submit" class="auth-primary-button min-h-12 px-5 text-sm font-semibold">Save student details</button>
                        @if ($student->account_status === 'pending')
                            <a href="{{ route('users.index', ['status' => 'pending']) }}" class="auth-link inline-flex min-h-11 items-center text-sm font-semibold focus-visible:outline focus-visible:outline-2 focus-visible:outline-accent-400">
                                Continue to account approval
                            </a>
                        @endif
                    </div>
                </form>
            </section>
        </main>
    </div>
</x-app-layout>
