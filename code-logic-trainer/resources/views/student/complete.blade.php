<x-app-layout>
    <div class="auth-shell relative isolate min-h-[calc(100vh-4rem)] overflow-hidden px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
        <div class="ambient-orb ambient-orb--violet" aria-hidden="true"></div>
        <div class="ambient-orb ambient-orb--indigo" aria-hidden="true"></div>
        <div class="ambient-orb ambient-orb--cyan" aria-hidden="true"></div>
        <main class="relative z-10 mx-auto max-w-3xl space-y-6">
            <section class="app-glass-panel rounded-2xl border p-6 text-center sm:p-8">
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-violet-300">Live assessment complete</p>
                <h1 class="mt-2 text-3xl font-bold text-[#F8FAFC]">Quiz complete</h1>
                <p class="mt-2 text-[#A59EB8]">{{ $session->quiz->title }}</p>
                <p class="my-6 text-4xl font-extrabold text-emerald-200 sm:text-5xl">
                    Score: {{ $participant->score }} / {{ $session->quiz->questions->count() }}
                </p>
                <div class="flex flex-col justify-center gap-3 sm:flex-row">
                    <a href="{{ route('student.dashboard') }}" class="app-secondary-button inline-flex min-h-11 items-center justify-center px-6 py-2 font-semibold text-[#E8E2F0]">
                        Practice
                    </a>
                    <a href="{{ route('student.live.join') }}" class="auth-primary-button inline-flex min-h-11 items-center justify-center px-6 py-2 font-semibold">
                        Join another quiz
                    </a>
                </div>
            </section>

            @include('student.answer-review', [
                'questions' => $session->quiz->questions,
                'answers' => $participant->answers ?? [],
            ])
        </main>
    </div>
</x-app-layout>
