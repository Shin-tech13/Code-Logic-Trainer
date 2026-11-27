<x-app-layout>
    <div class="auth-shell relative isolate min-h-[calc(100vh-4rem)] overflow-hidden px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
        <div class="ambient-orb ambient-orb--violet" aria-hidden="true"></div>
        <div class="ambient-orb ambient-orb--indigo" aria-hidden="true"></div>
        <div class="ambient-orb ambient-orb--plum" aria-hidden="true"></div>

        <main class="relative z-10 mx-auto max-w-7xl space-y-6">
            <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-sm font-semibold text-accent-300">Quiz analytics</p>
                    <h1 class="mt-2 text-3xl font-bold tracking-tight text-ink-text sm:text-4xl">{{ $quiz->title }}</h1>
                    <p class="mt-2 text-sm text-ink-subtle">Live assessments from your managed student cohort.</p>
                </div>
                <a href="{{ route('professor.quizzes.show', $quiz) }}" class="app-secondary-button inline-flex min-h-11 items-center justify-center px-4 text-sm font-semibold">Back to quiz</a>
            </header>

            <form method="GET" action="{{ route('professor.analytics.show', $quiz) }}" class="app-glass-panel grid gap-4 p-4 sm:grid-cols-2 sm:p-5 lg:grid-cols-4 lg:items-end">
                <div>
                    <label for="analytics-date-from" class="auth-label">From date</label>
                    <input id="analytics-date-from" name="date_from" type="date" value="{{ $filters['date_from'] ?? '' }}" class="auth-input mt-2">
                </div>
                <div>
                    <label for="analytics-date-to" class="auth-label">To date</label>
                    <input id="analytics-date-to" name="date_to" type="date" value="{{ $filters['date_to'] ?? '' }}" class="auth-input mt-2">
                </div>
                <div>
                    <label for="analytics-class" class="auth-label">Classroom</label>
                    <select id="analytics-class" name="class_id" class="auth-input mt-2">
                        <option value="">All managed students</option>
                        @foreach ($classrooms as $classroom)
                            <option value="{{ $classroom->id }}" @selected((string) ($filters['class_id'] ?? '') === (string) $classroom->id)>{{ $classroom->title }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex flex-wrap gap-3">
                    <button type="submit" class="auth-primary-button inline-flex min-h-11 flex-1 items-center justify-center px-4 text-sm font-semibold">Apply filters</button>
                    <a href="{{ route('professor.analytics.export', array_merge(['quiz' => $quiz->id], $filters)) }}" class="app-secondary-button inline-flex min-h-11 items-center justify-center px-4 text-sm font-semibold">Export CSV</a>
                </div>
            </form>

            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Quiz summary metrics">
                <article class="app-glass-panel border border-accent-300/15 bg-accent-400/[0.06] p-5">
                    <h2 class="text-sm font-medium text-ink-muted">Participation and completion</h2>
                    <p class="mt-3 text-3xl font-bold tabular-nums text-ink-text">{{ $participation['completion_rate'] === null ? '—' : number_format($participation['completion_rate'], 1).'%' }}</p>
                    <p class="mt-2 text-sm text-ink-subtle">{{ $participation['completed'] }} / {{ $participation['joined'] }} completed / joined</p>
                    <p class="mt-1 text-xs text-ink-faint">{{ $participation['label'] }}</p>
                </article>
                <article class="app-glass-panel p-5">
                    <h2 class="text-sm font-medium text-ink-muted">Mean vs. median score</h2>
                    <p class="mt-3 text-2xl font-bold tabular-nums text-ink-text">{{ $score['mean'] === null ? '—' : number_format($score['mean'], 1).'%' }} <span class="text-base font-medium text-ink-subtle">/</span> {{ $score['median'] === null ? '—' : number_format($score['median'], 1).'%' }}</p>
                    <p class="mt-2 text-sm text-ink-subtle">Completed participant scores</p>
                </article>
                <article class="app-glass-panel p-5">
                    <h2 class="text-sm font-medium text-ink-muted">Median response time</h2>
                    <p class="mt-3 text-3xl font-bold tabular-nums text-ink-text">{{ $median_response_seconds === null ? '—' : number_format($median_response_seconds, 1).'s' }}</p>
                    <p class="mt-2 text-sm text-ink-subtle">Per answered question</p>
                </article>
                <article class="app-glass-panel p-5">
                    <h2 class="text-sm font-medium text-ink-muted">Live session tab switches</h2>
                    <p class="mt-3 text-3xl font-bold tabular-nums text-amber-200">{{ number_format($tab_switch_count) }}</p>
                    <p class="mt-2 text-xs leading-5 text-ink-subtle">Monitoring signal. High switch counts indicate focus loss or external lookup, not proof of misconduct.</p>
                </article>
            </section>

            <div class="grid gap-6 xl:grid-cols-2">
                <section class="app-glass-panel p-5 sm:p-6" aria-labelledby="score-distribution-heading">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 id="score-distribution-heading" class="text-lg font-semibold text-ink-text">Score distribution</h2>
                            <p class="mt-1 text-sm text-ink-subtle">Completed attempts grouped by percentage score.</p>
                        </div>
                        <p class="text-sm text-ink-muted">First attempt <span class="font-semibold tabular-nums text-ink-text">{{ $score['first_mean'] === null ? '—' : number_format($score['first_mean'], 1).'%' }}</span> <span aria-hidden="true">→</span> latest <span class="font-semibold tabular-nums text-ink-text">{{ $score['latest_mean'] === null ? '—' : number_format($score['latest_mean'], 1).'%' }}</span></p>
                    </div>
                    @if ($score_distribution_suppressed)
                        <p class="mt-5 rounded-[var(--radius-control)] border border-amber-300/15 bg-amber-300/[0.04] p-4 text-sm text-amber-100">Score distribution is hidden until at least five distinct students have completed this quiz.</p>
                    @elseif ($participation['completed'] === 0)
                        <p class="mt-5 rounded-[var(--radius-control)] border border-white/10 bg-white/[0.025] p-4 text-sm text-ink-subtle">No completed live attempts match these filters yet.</p>
                    @else
                        <div class="mt-6 space-y-4">
                            @foreach ($score_distribution as $bucket => $count)
                                @php($width = $participation['completed'] > 0 ? ($count / $participation['completed']) * 100 : 0)
                                <div>
                                    <div class="mb-1.5 flex items-center justify-between gap-3 text-sm">
                                        <span class="text-ink-muted">{{ $bucket }}</span>
                                        <span class="tabular-nums text-ink-text">{{ $count }}</span>
                                    </div>
                                    <div class="h-2.5 overflow-hidden rounded-full bg-white/[0.07]" role="img" aria-label="{{ $bucket }}: {{ $count }} completed attempts">
                                        <div class="h-full rounded-full bg-accent-400/80" style="width: {{ $width }}%"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </section>

                <section class="app-glass-panel p-5 sm:p-6" aria-labelledby="topic-mastery-heading">
                    <div>
                        <h2 id="topic-mastery-heading" class="text-lg font-semibold text-ink-text">Learning gaps by topic and language</h2>
                        <p class="mt-1 text-sm text-ink-subtle">Self-study results from managed students; small cohorts are masked.</p>
                    </div>
                    @if ($topic_mastery->isEmpty())
                        <p class="mt-5 rounded-[var(--radius-control)] border border-white/10 bg-white/[0.025] p-4 text-sm text-ink-subtle">No completed self-study topic quizzes match this cohort and date range.</p>
                    @else
                        <div class="mt-5 max-h-72 space-y-4 overflow-y-auto pr-1">
                            @foreach ($topic_mastery as $topic)
                                @php($barColor = $topic['accuracy'] === null ? 'bg-ink-650' : ($topic['accuracy'] >= 80 ? 'bg-emerald-400' : ($topic['accuracy'] >= 60 ? 'bg-amber-300' : 'bg-rose-400')))
                                <div>
                                    <div class="mb-1.5 flex items-center justify-between gap-3 text-sm">
                                        <span class="min-w-0 truncate text-ink-soft">{{ $topic['language'] }} <span class="text-ink-faint">/</span> {{ $topic['topic'] }}</span>
                                        @if ($topic['suppressed'])
                                            <span class="shrink-0 text-xs text-ink-faint">Suppressed · fewer than 5 students</span>
                                        @else
                                            <span class="shrink-0 tabular-nums text-ink-text">{{ number_format($topic['accuracy'], 1) }}%</span>
                                        @endif
                                    </div>
                                    <div class="h-2 overflow-hidden rounded-full bg-white/[0.07]" role="img" aria-label="{{ $topic['suppressed'] ? 'Accuracy suppressed for small cohort' : $topic['language'].' '.$topic['topic'].' mastery '.number_format($topic['accuracy'], 1).' percent' }}">
                                        <div class="h-full rounded-full {{ $barColor }}" style="width: {{ $topic['suppressed'] ? 0 : $topic['accuracy'] }}%"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </section>
            </div>

            <section class="app-glass-panel overflow-hidden" aria-labelledby="question-diagnostics-heading">
                <header class="border-b border-white/10 p-5 sm:p-6">
                    <h2 id="question-diagnostics-heading" class="text-lg font-semibold text-ink-text">Question-level diagnostics</h2>
                    <p class="mt-1 text-sm text-ink-subtle">Distractor data is masked for fewer than five respondents.</p>
                </header>
                <div class="overflow-x-auto focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-[-3px] focus-visible:outline-accent-300" tabindex="0" role="region" aria-label="Scrollable question diagnostic table">
                    <table class="w-full min-w-[980px] text-left text-sm">
                        <thead class="bg-white/[0.025] text-xs font-semibold text-ink-muted">
                            <tr>
                                <th scope="col" class="px-5 py-3">Question snippet</th>
                                <th scope="col" class="px-4 py-3">Accuracy</th>
                                <th scope="col" class="px-4 py-3">Skip / timeout</th>
                                <th scope="col" class="px-4 py-3">Answer changes</th>
                                <th scope="col" class="px-4 py-3">Median time</th>
                                <th scope="col" class="px-5 py-3">Most chosen distractor</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/[0.07]">
                            @forelse ($questions as $question)
                                <tr class="transition-colors hover:bg-white/[0.025]">
                                    <td class="max-w-sm px-5 py-4">
                                        <pre class="whitespace-pre-wrap break-words font-mono text-xs leading-5 text-ink-soft">{{ $question['snippet'] }}</pre>
                                    </td>
                                    @if ($question['suppressed'])
                                        <td colspan="5" class="px-4 py-4 text-sm text-ink-faint">Breakdown suppressed · cohort below the five-student minimum</td>
                                    @else
                                        <td class="px-4 py-4 tabular-nums {{ $question['accuracy'] < 60 ? 'text-amber-200' : 'text-ink-soft' }}">{{ number_format($question['accuracy'], 1) }}%</td>
                                        <td class="px-4 py-4 tabular-nums text-ink-soft">{{ number_format($question['skip_timeout'], 1) }}%</td>
                                        <td class="px-4 py-4 tabular-nums text-ink-soft">{{ number_format($question['answer_change_rate'], 1) }}%</td>
                                        <td class="px-4 py-4 tabular-nums text-ink-soft">{{ $question['median_seconds'] === null ? '—' : number_format($question['median_seconds'], 1).'s' }}</td>
                                        <td class="px-5 py-4 {{ $question['confusing'] ? 'text-amber-200' : 'text-ink-soft' }}">
                                            {{ $question['distractor'] ?: '—' }}
                                            @if ($question['distractor'] && $question['distractor_rate'] !== null)
                                                <span class="ml-1 text-xs text-ink-subtle">({{ number_format($question['distractor_rate'], 1) }}%)</span>
                                            @endif
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-5 py-8 text-center text-sm text-ink-subtle">No questions are available for this quiz.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>
</x-app-layout>
