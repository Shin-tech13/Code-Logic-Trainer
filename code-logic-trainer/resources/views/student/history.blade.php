<x-app-layout>
    <div class="history-shell auth-shell relative isolate min-h-[calc(100vh-4rem)] overflow-hidden px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
        <div class="ambient-orb ambient-orb--violet" aria-hidden="true"></div>
        <div class="ambient-orb ambient-orb--indigo" aria-hidden="true"></div>
        <div class="ambient-orb ambient-orb--cyan" aria-hidden="true"></div>

        <main class="relative z-10 mx-auto max-w-5xl space-y-6">
            <header>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-violet-300">Your learning journey</p>
                <h1 class="mt-2 text-3xl font-bold tracking-tight text-[#F8FAFC]">Quiz and exam history</h1>
                <p class="mt-2 text-sm leading-6 text-[#B8AEC9]">Review completed self-study quizzes and live class assessments.</p>
            </header>

            <form method="GET" action="{{ route('student.history') }}" class="app-glass-panel grid gap-4 rounded-2xl border p-4 sm:grid-cols-2 sm:p-5 lg:grid-cols-4 lg:items-end">
                <div>
                    <label for="history-date-from" class="auth-label">From date</label>
                    <input id="history-date-from" name="date_from" type="date" value="{{ $filters['date_from'] ?? '' }}" class="auth-input mt-2">
                    <x-input-error :messages="$errors->get('date_from')" class="mt-2" />
                </div>
                <div>
                    <label for="history-date-to" class="auth-label">To date</label>
                    <input id="history-date-to" name="date_to" type="date" value="{{ $filters['date_to'] ?? '' }}" class="auth-input mt-2">
                    <x-input-error :messages="$errors->get('date_to')" class="mt-2" />
                </div>
                <div>
                    <label for="history-type" class="auth-label">Quiz type</label>
                    <select id="history-type" name="type" class="auth-input mt-2">
                        <option value="">All types</option>
                        <option value="self_study" @selected(($filters['type'] ?? '') === 'self_study')>Self-study</option>
                        <option value="live" @selected(($filters['type'] ?? '') === 'live')>Live class</option>
                    </select>
                    <x-input-error :messages="$errors->get('type')" class="mt-2" />
                </div>
                <div class="flex flex-wrap gap-3 sm:col-span-2 lg:col-span-1">
                    <button type="submit" class="auth-primary-button min-h-11 flex-1 px-4 py-2.5 text-sm font-semibold">Filter history</button>
                    <a href="{{ route('student.history') }}" class="app-secondary-button inline-flex min-h-11 items-center justify-center px-4 py-2.5 text-sm font-semibold">Clear</a>
                </div>
            </form>

            @forelse ($history as $attempt)
                @php($attemptId = 'history-attempt-'.$loop->index)
                <article class="app-glass-panel overflow-hidden rounded-2xl border">
                    <details class="history-attempt">
                        <summary class="cursor-pointer list-none p-5 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-[-3px] focus-visible:outline-violet-300 sm:p-6">
                            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h2 class="text-lg font-semibold text-[#F8FAFC]">{{ $attempt['title'] }}</h2>
                                        <span class="app-filter-chip">{{ $attempt['type'] === 'live' ? 'Live class' : 'Self-study' }}</span>
                                    </div>
                                    <p class="mt-1 text-sm text-[#A59EB8]">
                                        Taken {{ $attempt['taken_at']?->format('M j, Y \a\t g:i A') ?? 'Date unavailable' }}
                                    </p>
                                    <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-sm">
                                        <span class="font-semibold text-violet-100">Score: {{ $attempt['score'] }} / {{ $attempt['total'] }}</span>
                                        <span class="text-emerald-200">Correct: {{ $attempt['correct_count'] }}</span>
                                        <span class="text-rose-200">Incorrect: {{ $attempt['incorrect']->count() }}</span>
                                    </div>
                                </div>
                                <span class="history-expand-label shrink-0 text-sm font-semibold text-violet-200" aria-hidden="true">
                                    <span class="history-expand-label--closed">Review answers <span aria-hidden="true">+</span></span>
                                    <span class="history-expand-label--open">Hide answers <span aria-hidden="true">−</span></span>
                                </span>
                            </div>
                        </summary>

                        <div id="{{ $attemptId }}" class="border-t border-white/10 px-5 pb-5 pt-4 sm:px-6 sm:pb-6" x-data="{ answerView: 'incorrect' }">
                            @if ($attempt['incorrect']->isEmpty())
                                <p class="mb-4 rounded-xl border border-emerald-300/20 bg-emerald-300/[0.05] px-4 py-3 text-sm text-emerald-100">
                                    All questions were answered correctly.
                                </p>
                            @endif

                            @if ($attempt['total'] > 0)
                                <div class="mb-4 flex flex-wrap items-center gap-2" aria-label="Choose answers to display">
                                    <button
                                        type="button"
                                        @click="answerView = 'incorrect'"
                                        :aria-pressed="answerView === 'incorrect'"
                                        class="app-secondary-button min-h-10 px-3 py-2 text-sm font-semibold"
                                    >
                                        Incorrect answers ({{ $attempt['incorrect']->count() }})
                                    </button>
                                    <button
                                        type="button"
                                        @click="answerView = 'all'"
                                        :aria-pressed="answerView === 'all'"
                                        class="app-secondary-button min-h-10 px-3 py-2 text-sm font-semibold"
                                    >
                                        Review all answers ({{ $attempt['total'] }})
                                    </button>
                                </div>

                                <div class="space-y-4">
                                    @foreach ($attempt['answers'] as $item)
                                        <section
                                            x-cloak
                                            x-show="answerView === 'all' || ! {{ $item['is_correct'] ? 'true' : 'false' }}"
                                            class="rounded-xl border p-4 sm:p-5 {{ $item['is_correct'] ? 'border-emerald-300/20 bg-emerald-300/[0.035]' : 'border-rose-300/20 bg-rose-300/[0.04]' }}"
                                        >
                                            <div class="flex flex-wrap items-center justify-between gap-3">
                                                <h3 class="font-semibold text-[#F8FAFC]">Question {{ $item['number'] }}</h3>
                                                <span class="rounded-full border px-3 py-1 text-xs font-semibold {{ $item['is_correct'] ? 'border-emerald-300/25 bg-emerald-300/10 text-emerald-100' : 'border-rose-300/25 bg-rose-300/10 text-rose-100' }}">
                                                    {{ $item['is_correct'] ? 'Correct' : 'Incorrect' }}
                                                </span>
                                            </div>
                                            <x-quiz-content :content="$item['question']" class="mt-3" />

                                            <dl class="mt-4 space-y-4 text-sm">
                                                <div>
                                                    <dt class="mb-1 font-semibold text-[#D8CFE8]">Your answer</dt>
                                                    <dd class="rounded-lg border border-white/10 bg-black/10 p-3 {{ $item['is_correct'] ? 'text-emerald-100' : 'text-rose-100' }}">
                                                        <x-quiz-content :content="$item['answer']" />
                                                    </dd>
                                                </div>
                                                <div>
                                                    <dt class="mb-1 font-semibold text-[#D8CFE8]">Correct answer</dt>
                                                    <dd class="rounded-lg border border-emerald-300/15 bg-emerald-300/[0.04] p-3 text-emerald-100">
                                                        <x-quiz-content :content="$item['correct_answer']" />
                                                    </dd>
                                                </div>
                                                <div class="rounded-lg border border-violet-300/15 bg-violet-300/[0.035] p-3">
                                                    <dt class="mb-1 font-semibold text-violet-200">Explanation</dt>
                                                    <dd class="text-[#D8CFE8]">
                                                        <x-quiz-content :content="$item['explanation']" />
                                                    </dd>
                                                </div>
                                            </dl>
                                        </section>
                                    @endforeach
                                </div>
                            @else
                                <p class="text-sm text-[#A59EB8]">No answer details are available for this attempt.</p>
                            @endif
                        </div>
                    </details>
                </article>
            @empty
                <section class="app-glass-panel rounded-2xl border px-6 py-12 text-center">
                    <h2 class="text-lg font-semibold text-[#F8FAFC]">
                        {{ ($filters['date_from'] ?? '') || ($filters['date_to'] ?? '') || ($filters['type'] ?? '') ? 'No attempts match these filters' : 'No completed quizzes yet' }}
                    </h2>
                    <p class="mt-2 text-sm text-[#A59EB8]">
                        {{ ($filters['date_from'] ?? '') || ($filters['date_to'] ?? '') || ($filters['type'] ?? '') ? 'Try another date range or clear the filters to see your history.' : 'Your completed self-study quizzes and live assessments will appear here.' }}
                    </p>
                    <a href="{{ ($filters['date_from'] ?? '') || ($filters['date_to'] ?? '') || ($filters['type'] ?? '') ? route('student.history') : route('student.dashboard') }}" class="auth-primary-button mt-5 inline-flex items-center justify-center px-5 py-3 text-sm font-semibold">
                        {{ ($filters['date_from'] ?? '') || ($filters['date_to'] ?? '') || ($filters['type'] ?? '') ? 'Clear filters' : 'Start practicing' }}
                    </a>
                </section>
            @endforelse

            @if ($history->hasPages())
                <div class="rounded-2xl border border-white/10 bg-[#1A132B]/60 p-3">
                    {{ $history->links() }}
                </div>
            @endif
        </main>
    </div>
</x-app-layout>
