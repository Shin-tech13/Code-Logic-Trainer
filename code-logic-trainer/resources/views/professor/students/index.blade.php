<x-app-layout>
    <div class="auth-shell relative isolate min-h-[calc(100vh-4rem)] overflow-hidden px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
        <div class="ambient-orb ambient-orb--violet" aria-hidden="true"></div>
        <div class="ambient-orb ambient-orb--indigo" aria-hidden="true"></div>
        <div class="ambient-orb ambient-orb--cyan" aria-hidden="true"></div>
        <main class="relative z-10 mx-auto max-w-7xl space-y-6">
            <header>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-violet-300">Professor workspace</p>
                <h1 class="mt-2 text-3xl font-bold tracking-tight text-[#F8FAFC]">Managed students</h1>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-[#B8AEC9]">
                    Update student details and class assignments for students connected to your account.
                </p>
            </header>

            @if (session('status'))
                <div class="rounded-xl border border-emerald-300/20 bg-emerald-400/10 px-4 py-3 text-sm text-emerald-100" role="status">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-xl border border-rose-300/25 bg-rose-400/10 px-4 py-3 text-sm text-rose-100" role="alert">
                    <ul class="list-inside list-disc space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <section class="app-glass-panel rounded-2xl border p-5 sm:p-6">
                <h2 class="text-lg font-semibold text-[#F8FAFC]">Create a class</h2>
                <p class="mt-1 text-sm text-[#A59EB8]">A student needs an assigned class before you can approve their account.</p>
                <form method="POST" action="{{ route('professor.classrooms.store') }}" class="mt-4 grid gap-4 sm:grid-cols-[1fr_2fr_auto] sm:items-end">
                    @csrf
                    <div>
                        <label for="class-title" class="auth-label">Class name</label>
                        <input id="class-title" name="title" type="text" value="{{ old('title') }}" required maxlength="255" class="auth-input mt-2" placeholder="BSIT 2A">
                    </div>
                    <div>
                        <label for="class-description" class="auth-label">Description <span class="font-normal text-[#A59EB8]">(optional)</span></label>
                        <input id="class-description" name="description" type="text" value="{{ old('description') }}" maxlength="1000" class="auth-input mt-2" placeholder="Programming fundamentals">
                    </div>
                    <button type="submit" class="auth-primary-button min-h-12 px-5 text-sm font-semibold">Create class</button>
                </form>
                @if ($classrooms->isNotEmpty())
                    <p class="mt-4 text-xs text-[#A59EB8]">Your classes: {{ $classrooms->pluck('title')->join(', ') }}</p>
                @endif
            </section>

            <section class="app-glass-panel overflow-hidden rounded-2xl border" aria-label="Students managed by you">
                <div class="border-b border-white/10 px-5 py-4 sm:px-6">
                    <h2 class="font-semibold text-[#F8FAFC]">Students <span class="text-sm font-normal text-[#A59EB8]">({{ $students->count() }})</span></h2>
                </div>
                <div class="overflow-x-auto" role="region" aria-label="Managed students table. Scroll horizontally to view all columns." tabindex="0">
                    <table class="min-w-[760px] w-full text-left text-sm">
                        <thead class="bg-white/[0.035] text-xs uppercase tracking-wider text-[#BFB4D2]">
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
                            @forelse ($students as $student)
                                <tr class="transition-colors hover:bg-white/[0.035]">
                                    <td class="px-5 py-4 sm:px-6">
                                        <span class="block font-semibold text-[#F8FAFC]">{{ $student->name }}</span>
                                        <span class="mt-1 block font-mono text-xs text-[#B8AEC9]">{{ $student->email }}</span>
                                    </td>
                                    <td class="px-5 py-4 text-[#D8CFE8]">{{ $student->student_id ?: 'Not provided' }}</td>
                                    <td class="px-5 py-4 text-[#D8CFE8]">{{ $student->year_level ?: 'Not provided' }}</td>
                                    <td class="px-5 py-4 text-[#D8CFE8]">{{ $student->enrolledClassrooms->pluck('title')->join(', ') ?: 'Not assigned' }}</td>
                                    <td class="px-5 py-4">
                                        <span class="app-filter-chip">{{ ucfirst($student->account_status) }}</span>
                                    </td>
                                    <td class="px-5 py-4">
                                        <a href="{{ route('professor.students.edit', $student) }}" class="auth-link font-semibold focus-visible:outline focus-visible:outline-2 focus-visible:outline-violet-400">
                                            Manage details
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-6 py-12 text-center">
                                        <p class="font-semibold text-[#F8FAFC]">No managed students found.</p>
                                        <p class="mt-1 text-sm text-[#A59EB8]">Students you whitelist, create, or assign to one of your classes appear here after they register.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>
</x-app-layout>
