<x-app-layout>
    <div class="auth-shell relative isolate min-h-[calc(100vh-4rem)] overflow-hidden px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
        <div class="ambient-orb ambient-orb--violet" aria-hidden="true"></div>
        <div class="ambient-orb ambient-orb--indigo" aria-hidden="true"></div>
        <div class="ambient-orb ambient-orb--plum" aria-hidden="true"></div>

        <main class="relative z-10 mx-auto max-w-7xl space-y-6">
            <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-sm font-semibold text-accent-300">Your learning record</p>
                    <h1 class="mt-2 text-3xl font-bold tracking-tight text-ink-text sm:text-4xl">My progress</h1>
                    <p class="mt-2 text-sm text-ink-subtle">Review mastery across quizzes and coding challenges.</p>
                    <p class="mt-2 text-xs text-ink-faint">
                        Self-study completion:
                        {{ $progress['self_study_completion']['rate'] === null ? '—' : number_format($progress['self_study_completion']['rate'], 1).'%' }}
                        · {{ $progress['self_study_completion']['completed'] }} / {{ $progress['self_study_completion']['started'] }}
                        {{ $progress['self_study_completion']['label'] }}
                    </p>
                </div>
                <a href="{{ route('student.dashboard') }}" class="app-secondary-button inline-flex min-h-11 items-center justify-center px-4 text-sm font-semibold">Back to practice</a>
            </header>

            <div class="grid gap-6 xl:grid-cols-[1.3fr_0.7fr]">
                <section class="app-glass-panel p-5 sm:p-6" aria-labelledby="mastery-heading">
                    <div>
                        <h2 id="mastery-heading" class="text-lg font-semibold text-ink-text">Mastery by language and topic</h2>
                        <p class="mt-1 text-sm text-ink-subtle">Quiz results and passed coding challenges contribute to your learning record.</p>
                    </div>
                    @if ($progress['mastery']->isEmpty())
                        <div class="mt-5 rounded-[var(--radius-control)] border border-white/10 bg-white/[0.025] p-5">
                            <p class="text-sm text-ink-subtle">Complete a quiz or run a coding challenge to start building your progress view.</p>
                            <a href="{{ route('student.dashboard') }}" class="auth-primary-button mt-4 inline-flex min-h-10 items-center justify-center px-4 text-sm font-semibold">Start a topic quiz</a>
                        </div>
                    @else
                        <div class="mt-5 space-y-4">
                            @foreach ($progress['mastery'] as $item)
                                @php
                                    $tone = $item['accuracy'] >= 80 ? 'emerald' : ($item['accuracy'] >= 60 ? 'amber' : 'rose');
                                    $barColor = $tone === 'emerald' ? 'bg-emerald-400' : ($tone === 'amber' ? 'bg-amber-300' : 'bg-rose-400');
                                    $labelColor = $tone === 'emerald' ? 'text-emerald-200' : ($tone === 'amber' ? 'text-amber-200' : 'text-rose-200');
                                @endphp
                                <article class="rounded-[var(--radius-control)] border border-white/[0.08] bg-ink-950/20 p-4">
                                    <div class="flex flex-wrap items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <h3 class="font-semibold text-ink-text">{{ $item['topic'] }}</h3>
                                            <p class="mt-1 text-xs text-ink-subtle">{{ $item['language'] }} · {{ $item['attempts'] }} {{ \Illuminate\Support\Str::plural('assessment', $item['attempts']) }}</p>
                                        </div>
                                        <span class="text-right {{ $labelColor }}">
                                            <span class="block text-xs font-medium">{{ $item['accuracy'] >= 80 ? 'Strength' : ($item['accuracy'] >= 60 ? 'Focus area · watch' : 'Focus area') }}</span>
                                            <span class="mt-0.5 block tabular-nums">{{ number_format($item['accuracy'], 1) }}%</span>
                                        </span>
                                    </div>
                                    <div class="mt-3 h-2 overflow-hidden rounded-full bg-white/[0.07]" role="img" aria-label="{{ $item['language'].' '.$item['topic'].' mastery '.number_format($item['accuracy'], 1).' percent' }}">
                                        <div class="h-full rounded-full {{ $barColor }}" style="width: {{ min(100, max(0, $item['accuracy'])) }}%"></div>
                                    </div>
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        @foreach ($item['sources'] as $source)
                                            <span class="text-xs text-ink-faint">{{ $source === 'live' ? 'Live assessment' : ($source === 'challenge' ? 'Coding challenge' : 'Self-study') }}</span>
                                        @endforeach
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    @endif
                </section>

                <section class="app-glass-panel p-5 sm:p-6" aria-labelledby="challenge-insights-heading">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2 id="challenge-insights-heading" class="text-lg font-semibold text-ink-text">Coding challenge insights</h2>
                            <p class="mt-1 text-sm text-ink-subtle">{{ $progress['challenge_runs'] }} runs recorded</p>
                        </div>
                        @if ($progress['challenge_pass_rate'] !== null)
                            <p class="shrink-0 text-right text-xl font-bold tabular-nums text-emerald-200" aria-label="Overall challenge pass rate">{{ number_format($progress['challenge_pass_rate'], 1) }}%<span class="block text-xs font-medium text-ink-subtle">pass rate</span></p>
                        @endif
                    </div>
                    @if ($progress['challenge_runs'] === 0)
                        <p class="mt-5 rounded-[var(--radius-control)] border border-white/10 bg-white/[0.025] p-4 text-sm text-ink-subtle">Your challenge runs and recurring failure patterns will appear here.</p>
                    @else
                        <h3 class="mt-5 text-sm font-semibold text-ink-muted">Pass rate by difficulty</h3>
                        <div class="mt-3 space-y-3">
                            @foreach ($progress['challenge_difficulty'] as $item)
                                <div>
                                    <div class="mb-1 flex justify-between text-sm"><span class="text-ink-soft">{{ $item['difficulty'] }}</span><span class="tabular-nums text-ink-muted">{{ number_format($item['pass_rate'], 1) }}% <span class="text-ink-faint">· {{ $item['runs'] }} runs</span></span></div>
                                    <div class="h-2 overflow-hidden rounded-full bg-white/[0.07]" role="img" aria-label="{{ $item['difficulty'].' pass rate '.number_format($item['pass_rate'], 1).' percent' }}"><div class="h-full rounded-full bg-emerald-400" style="width: {{ $item['pass_rate'] }}%"></div></div>
                                </div>
                            @endforeach
                        </div>
                        <h3 class="mt-5 text-sm font-semibold text-ink-muted">Pass rate by concept</h3>
                        <ul class="mt-2 divide-y divide-white/[0.07]">
                            @foreach ($progress['challenge_concepts'] as $item)
                                <li class="flex items-center justify-between gap-3 py-2 text-sm"><span class="truncate text-ink-soft">{{ $item['concept'] }}</span><span class="shrink-0 tabular-nums text-ink-muted">{{ number_format($item['pass_rate'], 1) }}% <span class="text-ink-faint">({{ $item['runs'] }})</span></span></li>
                            @endforeach
                        </ul>
                        @if ($progress['challenge_failures']->isNotEmpty())
                            <h3 class="mt-5 text-sm font-semibold text-ink-muted">Recurring failure types</h3>
                            <ul class="mt-2 space-y-2">
                                @foreach ($progress['challenge_failures'] as $type => $count)
                                    <li class="flex justify-between gap-3 text-sm"><span class="text-amber-200">{{ str_replace('_', ' ', ucfirst($type)) }}</span><span class="tabular-nums text-ink-muted">{{ $count }}</span></li>
                                @endforeach
                            </ul>
                        @endif
                    @endif
                </section>
            </div>

            <section class="app-glass-panel p-5 sm:p-6" aria-labelledby="score-trend-heading">
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h2 id="score-trend-heading" class="text-lg font-semibold text-ink-text">Score trend and recent attempts</h2>
                        <p class="mt-1 text-sm text-ink-subtle">Select a bar to open that attempt in your answer history.</p>
                    </div>
                    <div class="flex gap-4 text-xs text-ink-muted" aria-label="Trend legend">
                        <span class="inline-flex items-center gap-2"><span class="h-2 w-2 rounded-full bg-accent-300"></span>Self-study</span>
                        <span class="inline-flex items-center gap-2"><span class="h-2 w-2 rounded-full bg-emerald-300"></span>Live assessment</span>
                    </div>
                </div>
                @if ($progress['attempts']->isEmpty())
                    <p class="mt-5 rounded-[var(--radius-control)] border border-white/10 bg-white/[0.025] p-4 text-sm text-ink-subtle">Completed assessments will build your score trend.</p>
                @else
                    <div class="mt-5 overflow-x-auto pb-2 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-300" tabindex="0" role="region" aria-label="Horizontally scrollable recent score trend">
                        <div class="flex min-w-max items-end gap-3 px-1 pt-2">
                            @foreach ($progress['attempts'] as $attempt)
                                <a href="{{ route('student.history', ['attempt_id' => $attempt['id'], 'type' => $attempt['type']]) }}#history-attempt-{{ $attempt['type'] }}-{{ $attempt['id'] }}"
                                    class="group flex w-20 flex-col items-center gap-2 rounded-md p-1 text-center focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-300"
                                    aria-label="{{ ($attempt['type'] === 'live' ? 'Live assessment' : 'Self-study').': '.$attempt['label'].', '.number_format($attempt['score'], 1).' percent, '.$attempt['taken_at']->format('M j, Y') }}"
                                    title="{{ $attempt['label'] }} · {{ number_format($attempt['score'], 1) }}%">
                                    <span class="text-xs tabular-nums text-ink-muted">{{ number_format($attempt['score'], 0) }}%</span>
                                    <span class="flex h-24 w-7 items-end overflow-hidden rounded-t-md bg-white/[0.06]">
                                        <span class="w-full rounded-t-sm {{ $attempt['type'] === 'live' ? 'bg-emerald-300/80' : 'bg-accent-300/80' }}" style="height: {{ min(100, max(4, $attempt['score'])) }}%"></span>
                                    </span>
                                    <span class="w-full truncate text-[11px] text-ink-subtle">{{ $attempt['taken_at']->format('M j') }}</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                    <ul class="mt-4 divide-y divide-white/[0.07]">
                        @foreach ($progress['attempts']->take(6) as $attempt)
                            <li class="flex flex-wrap items-center justify-between gap-2 py-3">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium text-ink-soft">{{ $attempt['label'] }}</p>
                                    <p class="mt-1 text-xs text-ink-subtle">{{ $attempt['type'] === 'live' ? 'Live assessment' : 'Self-study' }} · {{ $attempt['taken_at']->format('M j, Y') }}</p>
                                </div>
                                <a href="{{ route('student.history', ['attempt_id' => $attempt['id'], 'type' => $attempt['type']]) }}#history-attempt-{{ $attempt['type'] }}-{{ $attempt['id'] }}" class="shrink-0 text-sm font-semibold tabular-nums text-accent-200 hover:text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-accent-300">{{ number_format($attempt['score'], 1) }}% <span class="sr-only">Review answers</span></a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            @if ($progress['weakest_topic'])
                <section class="app-glass-panel flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between sm:p-6" aria-labelledby="next-step-heading">
                    <div>
                        <h2 id="next-step-heading" class="text-lg font-semibold text-ink-text">A useful next step</h2>
                        <p class="mt-1 text-sm text-ink-subtle">Your lowest recent quiz result is <span class="font-medium text-rose-200">{{ $progress['weakest_topic']['language'] }} · {{ $progress['weakest_topic']['topic'] }}</span> at {{ number_format($progress['weakest_topic']['accuracy'], 1) }}%.</p>
                    </div>
                    <a href="{{ route('student.dashboard', ['practice_topic' => $progress['weakest_topic']['topic'], 'practice_language' => $progress['weakest_topic']['language']]) }}" class="auth-primary-button inline-flex min-h-11 shrink-0 items-center justify-center px-5 text-sm font-semibold">Practice weakest topic</a>
                </section>
            @endif
        </main>
    </div>
</x-app-layout>
