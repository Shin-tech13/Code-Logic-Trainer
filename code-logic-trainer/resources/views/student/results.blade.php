<x-app-layout>
    <div class="auth-shell relative isolate min-h-[calc(100vh-4rem)] overflow-hidden px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
        <div class="ambient-orb ambient-orb--violet" aria-hidden="true"></div>
        <div class="ambient-orb ambient-orb--indigo" aria-hidden="true"></div>
        <div class="ambient-orb ambient-orb--plum" aria-hidden="true"></div>
        <main class="relative z-10 mx-auto max-w-3xl space-y-6">
            <section class="app-glass-panel p-6 text-center sm:p-8">
                <p class="text-xs font-semibold tracking-wide text-accent-300">Practice complete</p>
                <h1 class="mt-2 text-3xl font-bold tracking-tight text-ink-text">Quiz complete</h1>
                <p class="mt-2 text-sm leading-6 text-ink-subtle">{{ $session->language }}: {{ $session->topic }}</p>
                <p class="my-6 text-4xl font-extrabold tabular-nums text-emerald-200 sm:text-5xl">
                    Score: {{ $session->score }} / {{ $session->questions->count() }}
                </p>
                <a href="{{ route('student.dashboard') }}" class="auth-primary-button inline-flex min-h-11 items-center justify-center px-6 py-3 font-semibold">
                    Return to practice
                </a>
            </section>

            @include('student.answer-review', [
                'questions' => $session->questions,
                'answers' => $session->answers ?? [],
            ])
        </main>
    </div>
</x-app-layout>
