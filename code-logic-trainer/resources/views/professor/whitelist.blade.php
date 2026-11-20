<x-app-layout>
    <div class="auth-shell relative isolate min-h-[calc(100vh-4rem)] overflow-hidden px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
        <div class="ambient-orb ambient-orb--violet" aria-hidden="true"></div>
        <div class="ambient-orb ambient-orb--indigo" aria-hidden="true"></div>
        <div class="ambient-orb ambient-orb--cyan" aria-hidden="true"></div>
        <main class="relative z-10 mx-auto max-w-5xl space-y-6">
            <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-violet-300">Professor workspace</p>
                    <h1 class="mt-2 text-3xl font-bold tracking-tight text-[#F8FAFC]">Student whitelist</h1>
                    <p class="mt-2 text-sm leading-6 text-[#B8AEC9]">Manage who can access your student activities and live quizzes.</p>
                </div>
                <a href="{{ route('professor.students.index') }}" class="auth-link text-sm font-semibold focus-visible:outline focus-visible:outline-2 focus-visible:outline-violet-400">
                    Manage registered student details
                </a>
            </header>

            @if (session('status'))
                <div class="rounded-xl border border-emerald-300/20 bg-emerald-400/10 px-4 py-3 text-sm text-emerald-100" role="status">{{ session('status') }}</div>
            @endif
            @if (session('whitelist_errors'))
                <div class="rounded-xl border border-amber-300/20 bg-amber-300/10 px-4 py-3 text-sm text-amber-100" role="alert">
                    <ul class="list-inside list-disc space-y-1">
                        @foreach (session('whitelist_errors') as $err)
                            <li>{{ $err }}</li>
                        @endforeach
                    </ul>
                </div>
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
                <h2 class="text-lg font-semibold text-[#F8FAFC]">Add a student</h2>
                <p class="mt-1 text-sm text-[#A59EB8]">Add one student using their name and personal email address.</p>
                <form method="POST" action="{{ route('professor.whitelist.store') }}" class="mt-5 space-y-5">
                    @csrf
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="whitelist-name" class="auth-label">Student name</label>
                            <input id="whitelist-name" type="text" name="name" value="{{ old('name') }}" class="auth-input mt-2" placeholder="Juan Dela Cruz">
                        </div>
                        <div>
                            <label for="whitelist-email" class="auth-label">Student personal email</label>
                            <input id="whitelist-email" type="email" name="email" value="{{ old('email') }}" class="auth-input mt-2" placeholder="student@school.edu">
                        </div>
                    </div>

                    <button type="submit" class="auth-primary-button min-h-12 px-5 text-sm font-semibold">Add student</button>
                </form>
            </section>

            <section class="app-glass-panel rounded-2xl border p-5 sm:p-6">
                <div class="flex items-start gap-3">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-violet-300/20 bg-violet-400/10 font-mono text-sm text-violet-200" aria-hidden="true">{ }</span>
                    <div>
                        <h2 class="text-lg font-semibold text-[#F8FAFC]">Add multiple students</h2>
                        <p class="mt-1 text-sm text-[#A59EB8]">Paste email addresses separated by newlines, commas, or semicolons.</p>
                    </div>
                </div>
                <div class="mt-4 rounded-xl border border-white/10 bg-[#0F0B1A]/70 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wider text-[#BFB4D2]">Example</p>
                    <pre class="mt-2 overflow-x-auto font-mono text-sm leading-6 text-[#D8CFE8]"><code>student.one@school.edu
student.two@school.edu
student.three@school.edu</code></pre>
                </div>
                <form method="POST" action="{{ route('professor.whitelist.store') }}" class="mt-5 space-y-4">
                    @csrf
                    <div>
                        <label for="bulk_emails" class="auth-label">Student email addresses</label>
                        <textarea id="bulk_emails" name="bulk_emails" rows="5" class="auth-input mt-2 font-mono text-sm" placeholder="a@school.edu&#10;b@school.edu&#10;c@school.edu">{{ old('bulk_emails') }}</textarea>
                    </div>
                    <button type="submit" class="auth-primary-button min-h-12 px-5 text-sm font-semibold">Add email list</button>
                </form>
            </section>

            <section class="app-glass-panel overflow-hidden rounded-2xl border" aria-label="Whitelisted students">
                <div class="border-b border-white/10 px-5 py-4 sm:px-6">
                    <h2 class="font-semibold text-[#F8FAFC]">Whitelisted students <span class="text-sm font-normal text-[#A59EB8]">({{ $students->count() }})</span></h2>
                </div>
                @if ($students->isEmpty())
                    <p class="px-6 py-10 text-center text-sm text-[#A59EB8]">No students whitelisted yet.</p>
                @else
                    <div class="overflow-x-auto" role="region" aria-label="Whitelisted students table. Scroll horizontally to view all columns." tabindex="0">
                        <table class="min-w-[700px] w-full text-left text-sm">
                            <thead class="bg-white/[0.035] text-xs uppercase tracking-wider text-[#BFB4D2]">
                                <tr>
                                    <th scope="col" class="px-5 py-3 sm:px-6">Name</th>
                                    <th scope="col" class="px-5 py-3">Personal email</th>
                                    <th scope="col" class="px-5 py-3">Account</th>
                                    <th scope="col" class="px-5 py-3 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/[0.08]">
                                @foreach ($students as $student)
                                    <tr class="transition-colors hover:bg-white/[0.035]">
                                        <td class="px-5 py-3 font-medium text-[#F8FAFC] sm:px-6">{{ $student->name }}</td>
                                        <td class="px-5 py-3 text-[#D8CFE8]">{{ $student->email }}</td>
                                        <td class="px-5 py-3">
                                            @if ($student->user)
                                                <span class="app-filter-chip">Registered</span>
                                            @else
                                                <span class="text-[#A59EB8]">Not yet registered</span>
                                            @endif
                                        </td>
                                        <td class="px-5 py-3 text-right">
                                            <form method="POST" action="{{ route('professor.whitelist.destroy', $student->id) }}" onsubmit="return confirm(@js('Remove '.$student->email.' from the whitelist?'));">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="app-action-button rounded-lg text-rose-200 hover:bg-rose-300/10 hover:text-white" aria-label="Remove {{ $student->email }} from whitelist">
                                                    Remove
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        </main>
    </div>
</x-app-layout>
