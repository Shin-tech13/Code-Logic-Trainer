<x-app-layout>
    <div class="auth-shell relative isolate min-h-[calc(100vh-4rem)] overflow-hidden px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
        <div class="ambient-orb ambient-orb--violet" aria-hidden="true"></div>
        <div class="ambient-orb ambient-orb--indigo" aria-hidden="true"></div>
        <div class="ambient-orb ambient-orb--cyan" aria-hidden="true"></div>

        <main class="relative z-10 mx-auto max-w-5xl space-y-6">
            <header>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-violet-300">Your learning journey</p>
                <h1 class="mt-2 text-3xl font-bold tracking-tight text-[#F8FAFC]">Quiz and exam history</h1>
                <p class="mt-2 text-sm leading-6 text-[#B8AEC9]">Review completed self-study quizzes and live class assessments.</p>
            </header>

            @forelse ($history as $attempt)
                <article class="app-glass-panel rounded-2xl border p-5 sm:p-6">
                    <div class="flex flex-col gap-3 border-b border-white/10 pb-4 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-[#F8FAFC]">{{ $attempt['title'] }}</h2>
                            <p class="mt-1 text-sm text-[#A59EB8]">
                                Taken {{ $attempt['taken_at']?->format('M j, Y \a\t g:i A') ?? 'Date unavailable' }}
                            </p>
                        </div>
                        <p class="inline-flex w-fit items-center rounded-full border border-violet-300/25 bg-violet-400/10 px-3 py-1 text-sm font-semibold text-violet-100">
                            Score: {{ $attempt['score'] }} / {{ $attempt['total'] }}
                        </p>
                    </div>

                    @if ($attempt['incorrect']->isEmpty())
                        <p class="mt-4 text-sm text-emerald-200">All questions were answered correctly.</p>
                    @else
                        <div class="mt-5 space-y-4">
                            <h3 class="text-sm font-semibold uppercase tracking-wide text-[#D8CFE8]">Questions to review</h3>
                            @foreach ($attempt['incorrect'] as $index => $item)
                                <section class="rounded-xl border border-rose-200/15 bg-rose-300/[0.04] p-4 sm:p-5">
                                    <h4 class="font-medium leading-6 text-[#F8FAFC]">
                                        {{ $index + 1 }}. {{ $item['question'] }}
                                    </h4>
                                    <dl class="mt-3 space-y-2 text-sm leading-6">
                                        <div>
                                            <dt class="inline font-semibold text-[#D8CFE8]">Your answer: </dt>
                                            <dd class="inline break-words text-rose-100">{{ $item['answer'] }}</dd>
                                        </div>
                                        <div>
                                            <dt class="inline font-semibold text-[#D8CFE8]">Correct answer: </dt>
                                            <dd class="inline break-words text-emerald-100">{{ $item['correct_answer'] }}</dd>
                                        </div>
                                        <div class="rounded-lg border border-white/10 bg-black/10 px-3 py-2">
                                            <dt class="font-semibold text-violet-200">Explanation</dt>
                                            <dd class="mt-1 text-[#D8CFE8]">{{ $item['explanation'] }}</dd>
                                        </div>
                                    </dl>
                                </section>
                            @endforeach
                        </div>
                    @endif
                </article>
            @empty
                <section class="app-glass-panel rounded-2xl border px-6 py-12 text-center">
                    <h2 class="text-lg font-semibold text-[#F8FAFC]">No completed quizzes yet</h2>
                    <p class="mt-2 text-sm text-[#A59EB8]">Your completed self-study quizzes and live assessments will appear here.</p>
                    <a href="{{ route('student.dashboard') }}" class="auth-primary-button mt-5 inline-flex items-center justify-center px-5 py-3 text-sm font-semibold">
                        Start practicing
                    </a>
                </section>
            @endforelse
        </main>
    </div>
</x-app-layout>
