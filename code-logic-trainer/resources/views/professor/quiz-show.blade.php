<x-app-layout>
    <div class="auth-shell relative isolate min-h-[calc(100vh-4rem)] overflow-hidden px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
        <div class="ambient-orb ambient-orb--violet" aria-hidden="true"></div>
        <div class="ambient-orb ambient-orb--indigo" aria-hidden="true"></div>
        <div class="ambient-orb ambient-orb--cyan" aria-hidden="true"></div>

        <main class="relative z-10 mx-auto max-w-5xl space-y-6">
            <header class="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-violet-300">Quiz review</p>
                    <h1 class="mt-2 break-words text-3xl font-bold tracking-tight text-[#F8FAFC]">{{ $quiz->title }}</h1>
                    @if ($quiz->description)
                        <p class="mt-2 max-w-2xl text-sm leading-6 text-[#A59EB8]">{{ $quiz->description }}</p>
                    @endif
                    <p class="mt-2 text-sm text-[#C8BED6]">
                        {{ $quiz->questions->count() }} questions
                        <span aria-hidden="true">·</span>
                        {{ str_replace('_', ' ', ucfirst($quiz->quiz_format ?? 'multiple_choice')) }}
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <a href="{{ route('professor.dashboard') }}" class="app-secondary-button inline-flex min-h-10 items-center px-4 text-sm font-semibold text-[#E8E2F0]">
                        Back to dashboard
                    </a>
                    <form method="POST" action="{{ route('professor.sessions.launch', $quiz->id) }}">
                        @csrf
                        <button type="submit" class="auth-primary-button inline-flex min-h-10 items-center px-4 text-sm font-semibold">
                            Start live session
                        </button>
                    </form>
                </div>
            </header>

            <div class="space-y-4">
                @foreach ($quiz->questions as $index => $question)
                    <article class="app-glass-panel rounded-2xl border p-5 sm:p-6">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <h2 class="font-semibold text-[#F8FAFC]">Question {{ $index + 1 }}</h2>
                            @if ($question->needs_review)
                                <span class="rounded-full border border-amber-300/25 bg-amber-300/10 px-3 py-1 text-xs font-semibold text-amber-100">
                                    Professor review recommended
                                </span>
                            @endif
                        </div>

                        <pre class="mt-4 whitespace-pre-wrap break-words rounded-xl border border-white/10 bg-[#0F0B1A]/75 p-4 font-mono text-sm text-[#E8E2F0]">{{ $question->question_text }}</pre>

                        @if (($question->question_type ?? 'multiple_choice') === 'multiple_choice')
                            <div class="mt-4 grid gap-2 sm:grid-cols-2">
                                @foreach ($question->options ?? [] as $option)
                                    <div class="rounded-lg border px-3 py-2 text-sm {{ $option === $question->correct_answer ? 'border-emerald-300/25 bg-emerald-300/[0.06] text-emerald-100' : 'border-white/10 bg-white/[0.02] text-[#D8CFE8]' }}">
                                        {{ $option }}
                                        @if ($option === $question->correct_answer)
                                            <span class="ml-1 font-semibold">(Correct)</span>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="mt-4 rounded-xl border border-emerald-300/20 bg-emerald-300/[0.05] p-4 text-sm text-emerald-100">
                                <span class="font-semibold">Accepted answer(s):</span>
                                {{ implode(' · ', $question->accepted_answers ?? [$question->correct_answer]) }}
                            </div>
                        @endif

                        <div class="mt-4 rounded-xl border border-white/10 bg-black/10 p-4 text-sm leading-6 text-[#D8CFE8]">
                            <span class="font-semibold text-violet-200">Explanation:</span>
                            {{ filled($question->explanation) ? $question->explanation : 'No explanation provided.' }}
                        </div>
                    </article>
                @endforeach
            </div>
        </main>
    </div>
</x-app-layout>
