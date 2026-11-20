<x-app-layout>
    <div class="auth-shell relative isolate min-h-[calc(100vh-4rem)] overflow-hidden px-4 py-10 sm:px-6 lg:px-8">
        <div class="ambient-orb ambient-orb--violet" aria-hidden="true"></div>
        <div class="ambient-orb ambient-orb--indigo" aria-hidden="true"></div>
        <div class="ambient-orb ambient-orb--cyan" aria-hidden="true"></div>

        <main class="relative z-10 mx-auto max-w-md">
            <section class="app-glass-panel rounded-2xl border p-6 sm:p-8">
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-violet-300">Classroom assessment</p>
                <h1 class="mt-2 text-2xl font-bold text-[#F8FAFC]">Join a live quiz</h1>
                <p class="mt-2 text-sm leading-6 text-[#A59EB8]">Enter the quiz code provided by your professor.</p>

                <form method="POST" action="{{ route('student.live.join.store') }}" class="mt-6 space-y-4">
                    @csrf

                    @if ($errors->any())
                        <div class="rounded-xl border border-rose-300/25 bg-rose-400/10 px-4 py-3 text-sm text-rose-100" role="alert">
                            {{ $errors->first('quiz_code') }}
                        </div>
                    @endif

                    <div>
                        <label for="quiz_code" class="auth-label">Quiz code</label>
                        <input type="text" id="quiz_code" name="quiz_code" required maxlength="20" autocomplete="off"
                            class="auth-input mt-2 text-center font-mono text-2xl tracking-widest uppercase"
                            placeholder="QZ-1234" aria-describedby="quiz-code-help">
                        <p id="quiz-code-help" class="mt-2 text-xs text-[#A59EB8]">Enter the code exactly as shown by your professor.</p>
                    </div>

                    <button type="submit" class="auth-primary-button inline-flex min-h-12 w-full items-center justify-center px-5 font-semibold">
                        Join quiz
                    </button>
                </form>
            </section>
        </main>
    </div>
</x-app-layout>
