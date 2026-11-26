<x-app-layout>
    <div class="auth-shell relative isolate min-h-[calc(100vh-4rem)] overflow-hidden px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
        <div class="ambient-orb ambient-orb--violet" aria-hidden="true"></div>
        <div class="ambient-orb ambient-orb--indigo" aria-hidden="true"></div>
        <div class="ambient-orb ambient-orb--plum" aria-hidden="true"></div>

        <main class="relative z-10 mx-auto max-w-5xl space-y-6">
            <header class="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0">
                    <p class="text-xs font-semibold tracking-wide text-accent-300">Quiz review</p>
                    <h1 class="mt-2 break-words text-3xl font-bold tracking-tight text-ink-text">{{ $quiz->title }}</h1>
                    @if ($quiz->description)
                        <p class="mt-2 max-w-2xl text-sm leading-6 text-ink-subtle">{{ $quiz->description }}</p>
                    @endif
                    <p class="mt-2 text-sm text-ink-muted">
                        {{ $quiz->questions->count() }} questions
                        <span aria-hidden="true">·</span>
                        {{ str_replace('_', ' ', ucfirst($quiz->quiz_format ?? 'multiple_choice')) }}
                        <span aria-hidden="true">·</span>
                        {{ $quiz->seconds_per_question }} seconds per question
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <a href="{{ route('professor.dashboard') }}" class="app-secondary-button inline-flex min-h-11 items-center px-4 text-sm font-semibold text-ink-soft">
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

            @if (session('status'))
                <div class="rounded-[var(--radius-control)] border border-emerald-300/20 bg-emerald-400/10 px-4 py-3 text-sm leading-5 text-emerald-100" role="status" aria-live="polite">
                    {{ session('status') }}
                </div>
            @endif

            <section class="app-glass-panel p-5 sm:p-6" aria-labelledby="question-timer-heading">
                <div class="mb-4">
                    <h2 id="question-timer-heading" class="text-lg font-semibold tracking-tight text-ink-text">Question timer</h2>
                    <p class="mt-1 text-sm leading-6 text-ink-subtle">Set the default time allowed for each question in new live sessions.</p>
                </div>
                <form method="POST" action="{{ route('professor.quizzes.settings.update', $quiz->id) }}"
                    x-data
                    @submit="$refs.secondsPerQuestion.value = window.normalizeQuestionTimer($refs.secondsPerQuestion.value, true)">
                    @csrf
                    @method('PATCH')
                    <label for="quiz-seconds-per-question" class="auth-label">Seconds per question</label>
                    <div class="mt-2 flex flex-col gap-3 sm:flex-row sm:items-end">
                        <div class="w-full sm:max-w-xs">
                            <input id="quiz-seconds-per-question" x-ref="secondsPerQuestion" name="seconds_per_question" type="number" min="0" max="100" step="1"
                                value="{{ old('seconds_per_question', $quiz->seconds_per_question) }}" placeholder="20 (default)" class="auth-input"
                                @input="$event.target.value = window.normalizeQuestionTimer($event.target.value)"
                                @blur="$event.target.value = window.normalizeQuestionTimer($event.target.value, true)">
                        </div>
                        <button type="submit" class="auth-primary-button inline-flex min-h-11 items-center justify-center px-5 text-sm font-semibold">Save timer</button>
                    </div>
                    <p class="mt-2 text-xs leading-5 text-ink-subtle">1–100s (leave blank or 0 for default 20s; values above 100 cap at 100s)</p>
                    @error('seconds_per_question')
                        <p class="auth-input-error mt-2 text-sm" role="alert">{{ $message }}</p>
                    @enderror
                </form>
            </section>

            <div class="space-y-4">
                @foreach ($quiz->questions as $index => $question)
                    <article class="app-glass-panel p-5 sm:p-6">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <h2 class="font-semibold text-ink-text">Question {{ $index + 1 }}</h2>
                            @if ($question->needs_review)
                                <span class="rounded-full border border-amber-300/25 bg-amber-300/10 px-3 py-1 text-xs font-semibold text-amber-100">
                                    Professor review recommended
                                </span>
                            @endif
                        </div>

                        <pre class="mt-4 whitespace-pre-wrap break-words rounded-[var(--radius-control)] border border-white/10 bg-ink-950/70 p-4 font-mono text-sm leading-6 text-ink-soft">{{ $question->question_text }}</pre>

                        @if (($question->question_type ?? 'multiple_choice') === 'multiple_choice')
                            <div class="mt-4 grid gap-2 sm:grid-cols-2">
                                @foreach ($question->options ?? [] as $option)
                                    <div class="rounded-[var(--radius-field)] border px-3 py-2 text-sm leading-5 {{ $option === $question->correct_answer ? 'border-emerald-300/25 bg-emerald-300/[0.06] text-emerald-100' : 'border-white/10 bg-white/[0.02] text-ink-soft' }}">
                                        {{ $option }}
                                        @if ($option === $question->correct_answer)
                                            <span class="ml-1 font-semibold">(Correct)</span>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="mt-4 rounded-[var(--radius-control)] border border-emerald-300/20 bg-emerald-300/[0.05] p-4 text-sm leading-6 text-emerald-100">
                                <span class="font-semibold">Accepted answer(s):</span>
                                {{ implode(' · ', $question->accepted_answers ?? [$question->correct_answer]) }}
                            </div>
                        @endif

                        <div class="mt-4 rounded-[var(--radius-control)] border border-white/10 bg-ink-950/20 p-4 text-sm leading-6 text-ink-soft">
                            <span class="font-semibold text-accent-200">Explanation:</span>
                            {{ filled($question->explanation) ? $question->explanation : 'No explanation provided.' }}
                        </div>
                    </article>
                @endforeach
            </div>
        </main>
    </div>
</x-app-layout>
