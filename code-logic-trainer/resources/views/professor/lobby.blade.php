<x-app-layout>
    <div class="mx-auto max-w-3xl space-y-6 px-4 py-8 sm:px-6 sm:py-10"
        x-data="{
            status: '{{ $session->status }}',
            participants: [],
            quizCode: '{{ $session->quiz_code }}',
            secondsPerQuestion: {{ $session->seconds_per_question }},
            analytics: @js($analytics),
            analyticsError: '',
            activeDimension: 'students',
            orientation: 'bar',
            _timer: null,
            chartRows() {
                return this.analytics[this.activeDimension] || [];
            },
            chartSeries() {
                if (this.activeDimension === 'students') {
                    return [
                        { key: 'answered_count', label: 'Answered', color: 'bg-accent-500' },
                        { key: 'remaining_count', label: 'Remaining', color: 'bg-ink-650' },
                    ];
                }

                return [
                    { key: 'total_count', label: 'Questions', color: 'bg-ink-650' },
                    { key: 'average_answered_count', label: 'Avg. answered', color: 'bg-accent-300' },
                    { key: 'average_correct_count', label: 'Avg. correct', color: 'bg-accent-500' },
                ];
            },
            chartMaximum() {
                return Math.max(1, ...this.chartRows().map(row => Number(row.total_count) || 0));
            },
            seriesValue(row, key) {
                return Number(row[key]) || 0;
            },
            seriesPercent(row, key) {
                return Math.min(100, (this.seriesValue(row, key) / this.chartMaximum()) * 100);
            },
            formatCount(value) {
                const count = Number(value) || 0;
                return Number.isInteger(count) ? String(count) : count.toFixed(1);
            },
            seriesTooltip(row, series) {
                const value = this.seriesValue(row, series.key);
                const total = Number(row.total_count) || 0;
                const percent = total > 0 ? Math.round((value / total) * 100) : 0;
                return `${row.label}: ${series.label}, ${this.formatCount(value)} of ${total} questions (${percent}%).`;
            },
            rowSummary(row) {
                if (this.activeDimension === 'students') {
                    return `${row.correct_count} correct · ${row.completion_percent}% complete · ${row.accuracy_percent}% accuracy`;
                }

                return `Avg. answered ${this.formatCount(row.average_answered_count)} · avg. correct ${this.formatCount(row.average_correct_count)} · ${row.completion_percent}% complete · ${row.accuracy_percent}% accuracy`;
            },
            _poll() {
                fetch('{{ route('professor.sessions.status', $session->id) }}', {
                    headers: { 'Accept': 'application/json' }
                })
                .then(response => {
                    if (!response.ok) throw new Error('Could not refresh live quiz analytics.');
                    return response.json();
                })
                .then(data => {
                    this.status = data.status;
                    this.participants = data.participants;
                    this.quizCode = data.quiz_code;
                    this.secondsPerQuestion = data.seconds_per_question;
                    this.analytics = data.analytics;
                    this.analyticsError = '';
                })
                .catch(() => {
                    this.analyticsError = 'Live analytics could not refresh. The displayed values may be out of date.';
                });
            },
            init() {
                this._poll();
                this._timer = setInterval(() => this._poll(), 3000);
            },
            async startQuiz() {
                const resp = await fetch('{{ route('professor.sessions.start', $session->id) }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({}),
                });
                const data = await resp.json();
                if (data.success) {
                    this.status = 'in_progress';
                    window.location.reload();
                }
            },
            endQuiz() {
                if (!confirm('End this session?')) return;
                document.getElementById('end-form').submit();
            }
        }">

        <div class="app-glass-panel p-6 text-center sm:p-8">
            <p class="mb-2 text-sm text-ink-subtle">Quiz code</p>
            <p class="text-4xl font-extrabold tracking-widest text-accent-200 sm:text-5xl" x-text="quizCode"></p>
            <p class="mt-4 text-sm text-ink-muted">{{ $session->quiz->title }}</p>
            <p class="mt-2 text-sm text-ink-subtle" x-text="secondsPerQuestion + ' seconds per question'"></p>

            <div class="mt-4">
                <template x-if="status === 'waiting'">
                    <span class="inline-block bg-amber-100 text-amber-800 px-3 py-1 rounded-full text-sm font-semibold">Waiting for students…</span>
                </template>
                <template x-if="status === 'in_progress'">
                    <span class="inline-block bg-green-100 text-green-800 px-3 py-1 rounded-full text-sm font-semibold">In Progress</span>
                </template>
                <template x-if="status === 'completed'">
                    <span class="inline-block bg-gray-200 text-gray-700 px-3 py-1 rounded-full text-sm font-semibold">Completed</span>
                </template>
            </div>
        </div>

        @if ($session->status === 'waiting')
            <section class="app-glass-panel p-5 sm:p-6" aria-labelledby="session-timer-heading">
                <div class="mb-4">
                    <h2 id="session-timer-heading" class="text-lg font-semibold tracking-tight text-ink-text">Session timer</h2>
                    <p class="mt-1 text-sm leading-6 text-ink-subtle">Adjust the time allowed for each question before the exam starts.</p>
                </div>
                <form method="POST" action="{{ route('professor.sessions.timer.update', $session->id) }}"
                    x-data
                    @submit="$refs.secondsPerQuestion.value = window.normalizeQuestionTimer($refs.secondsPerQuestion.value, true)">
                    @csrf
                    @method('PATCH')
                    <label for="session-seconds-per-question" class="auth-label">Seconds per question</label>
                    <div class="mt-2 flex flex-col gap-3 sm:flex-row sm:items-end">
                        <div class="w-full sm:max-w-xs">
                            <input id="session-seconds-per-question" x-ref="secondsPerQuestion" name="seconds_per_question" type="number" min="0" max="100" step="1"
                                value="{{ old('seconds_per_question', $session->seconds_per_question) }}" placeholder="20 (default)" class="auth-input"
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
        @endif

        <div class="app-glass-panel p-5 text-center sm:p-6">
            <template x-if="status === 'waiting'">
                <button @click="startQuiz"
                    class="inline-flex min-h-12 items-center justify-center rounded-[var(--radius-control)] bg-emerald-600 px-8 py-3 text-lg font-bold text-white transition-colors duration-base hover:bg-emerald-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-200">
                    Official Start Quiz
                </button>
            </template>
            <template x-if="status === 'in_progress'">
                <button @click="endQuiz"
                    class="inline-flex min-h-12 items-center justify-center rounded-[var(--radius-control)] bg-rose-700 px-8 py-3 text-lg font-bold text-white transition-colors duration-base hover:bg-rose-600 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-rose-200">
                    End Session
                </button>
            </template>
            <p class="mt-3 text-sm leading-6 text-ink-subtle">
                Students can join at any time — even after the official start.
            </p>
        </div>

        <section class="app-glass-panel p-5 sm:p-6" aria-labelledby="live-analytics-heading">
            <header class="flex flex-col gap-4 border-b border-white/10 pb-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <h2 id="live-analytics-heading" class="text-lg font-semibold tracking-tight text-ink-text">Question analytics</h2>
                    <p class="mt-1 text-sm leading-6 text-ink-subtle">Compare live progress, answer accuracy, and question coverage.</p>
                </div>
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <div class="flex flex-wrap gap-2" role="group" aria-label="Analytics dimension">
                        <button type="button" @click="activeDimension = 'students'" :aria-pressed="activeDimension === 'students'"
                            :class="activeDimension === 'students' ? 'border-accent-300/35 bg-accent-400/10 text-accent-100' : 'border-white/10 bg-white/[0.03] text-ink-muted hover:text-ink-text'"
                            class="min-h-10 rounded-[var(--radius-field)] border px-3 text-sm font-medium transition-colors duration-quick focus-visible:outline focus-visible:outline-2 focus-visible:outline-accent-400">
                            By student
                        </button>
                        <button type="button" @click="activeDimension = 'topics'" :aria-pressed="activeDimension === 'topics'"
                            :class="activeDimension === 'topics' ? 'border-accent-300/35 bg-accent-400/10 text-accent-100' : 'border-white/10 bg-white/[0.03] text-ink-muted hover:text-ink-text'"
                            class="min-h-10 rounded-[var(--radius-field)] border px-3 text-sm font-medium transition-colors duration-quick focus-visible:outline focus-visible:outline-2 focus-visible:outline-accent-400">
                            By topic
                        </button>
                        <button type="button" @click="activeDimension = 'difficulties'" :aria-pressed="activeDimension === 'difficulties'"
                            :class="activeDimension === 'difficulties' ? 'border-accent-300/35 bg-accent-400/10 text-accent-100' : 'border-white/10 bg-white/[0.03] text-ink-muted hover:text-ink-text'"
                            class="min-h-10 rounded-[var(--radius-field)] border px-3 text-sm font-medium transition-colors duration-quick focus-visible:outline focus-visible:outline-2 focus-visible:outline-accent-400">
                            By difficulty
                        </button>
                    </div>
                    <div class="flex gap-2" role="group" aria-label="Chart orientation">
                        <button type="button" @click="orientation = 'column'" :aria-pressed="orientation === 'column'"
                            :class="orientation === 'column' ? 'border-accent-300/35 bg-accent-400/10 text-accent-100' : 'border-white/10 bg-white/[0.03] text-ink-muted hover:text-ink-text'"
                            class="min-h-10 rounded-[var(--radius-field)] border px-3 text-sm font-medium transition-colors duration-quick focus-visible:outline focus-visible:outline-2 focus-visible:outline-accent-400">
                            Column chart
                        </button>
                        <button type="button" @click="orientation = 'bar'" :aria-pressed="orientation === 'bar'"
                            :class="orientation === 'bar' ? 'border-accent-300/35 bg-accent-400/10 text-accent-100' : 'border-white/10 bg-white/[0.03] text-ink-muted hover:text-ink-text'"
                            class="min-h-10 rounded-[var(--radius-field)] border px-3 text-sm font-medium transition-colors duration-quick focus-visible:outline focus-visible:outline-2 focus-visible:outline-accent-400">
                            Bar chart
                        </button>
                    </div>
                </div>
            </header>

            <p x-show="analyticsError" x-cloak x-text="analyticsError" class="mt-3 text-sm text-amber-200" role="status" aria-live="polite"></p>

            <div class="mt-4 flex flex-wrap gap-x-5 gap-y-2" aria-label="Chart legend">
                <template x-for="series in chartSeries()" :key="series.key">
                    <span class="inline-flex items-center gap-2 text-xs text-ink-muted">
                        <span class="h-2.5 w-2.5 rounded-sm" :class="series.color" aria-hidden="true"></span>
                        <span x-text="series.label"></span>
                    </span>
                </template>
            </div>

            <div x-show="analytics.question_count === 0" x-cloak class="mt-5 rounded-[var(--radius-control)] border border-white/10 bg-ink-950/40 px-4 py-8 text-center">
                <p class="font-medium text-ink-soft">No questions to chart yet</p>
                <p class="mt-1 text-sm text-ink-subtle">Analytics will appear when this quiz has questions.</p>
            </div>

            <div x-show="analytics.question_count > 0 && activeDimension === 'students' && analytics.participant_count === 0" x-cloak
                class="mt-5 rounded-[var(--radius-control)] border border-white/10 bg-ink-950/40 px-4 py-8 text-center">
                <p class="font-medium text-ink-soft">No student attempts yet</p>
                <p class="mt-1 text-sm text-ink-subtle">Student progress will appear here as answers are recorded.</p>
            </div>

            <p x-show="analytics.question_count > 0 && activeDimension !== 'students' && analytics.participant_count === 0" x-cloak
                class="mt-4 text-xs leading-5 text-ink-subtle" role="status">
                No attempts recorded yet; class averages are zero.
            </p>

            <div x-show="analytics.question_count > 0 && analytics.participant_count > 0 || (analytics.question_count > 0 && activeDimension !== 'students')" x-cloak class="mt-5">
                <div x-show="orientation === 'bar'" class="mb-4 space-y-3">
                    <template x-for="row in chartRows()" :key="row.id || row.label">
                        <article class="rounded-[var(--radius-control)] border border-white/[0.08] bg-ink-950/35 p-3 sm:p-4">
                            <div class="mb-3 flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                                <h3 class="min-w-0 break-words text-sm font-semibold text-ink-soft" x-text="row.label"></h3>
                                <p class="text-xs tabular-nums text-ink-subtle" x-text="rowSummary(row)"></p>
                            </div>

                            <div class="space-y-2.5" role="group" :aria-label="row.label + ' question counts'">
                                <template x-for="series in chartSeries()" :key="series.key">
                                    <div class="grid grid-cols-[7rem_minmax(0,1fr)_2.5rem] items-center gap-2 sm:grid-cols-[8rem_minmax(0,1fr)_3rem]">
                                        <span class="truncate text-xs text-ink-muted" x-text="series.label"></span>
                                        <div class="h-2.5 overflow-hidden rounded-full bg-white/[0.07]" role="img"
                                            :aria-label="seriesTooltip(row, series)" :title="seriesTooltip(row, series)">
                                            <div class="h-full rounded-full transition-[width] duration-quick ease-craft-out" :class="series.color"
                                                :style="'width: ' + seriesPercent(row, series.key) + '%'"></div>
                                        </div>
                                        <span class="text-right font-mono text-xs tabular-nums text-ink-soft" x-text="formatCount(seriesValue(row, series.key))"></span>
                                    </div>
                                </template>
                            </div>
                        </article>
                    </template>
                </div>

                <div x-show="orientation === 'column'" class="overflow-x-auto pb-2" role="group" aria-label="Column chart">
                    <div class="flex min-h-[15rem] min-w-max items-end gap-3 border-b border-white/10 px-2">
                        <template x-for="row in chartRows()" :key="row.id || row.label">
                            <div class="flex w-28 flex-col items-center gap-2 pb-3">
                                <div class="flex h-40 w-full items-end justify-center gap-1.5">
                                    <template x-for="series in chartSeries()" :key="series.key">
                                        <div class="flex h-full w-7 flex-col items-center justify-end gap-1">
                                            <span class="font-mono text-[10px] tabular-nums text-ink-muted" x-text="formatCount(seriesValue(row, series.key))"></span>
                                            <div class="relative h-32 w-full overflow-hidden rounded-t-[var(--radius-field)] bg-white/[0.04]"
                                                role="img" :aria-label="seriesTooltip(row, series)" :title="seriesTooltip(row, series)">
                                                <div class="absolute inset-x-0 bottom-0 rounded-t-[var(--radius-field)] transition-[height] duration-quick ease-craft-out"
                                                    :class="series.color"
                                                    :style="'height: ' + seriesPercent(row, series.key) + '%'"></div>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                                <p class="w-full break-words text-center text-xs font-medium leading-4 text-ink-soft" x-text="row.label"></p>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </section>

        <div class="app-glass-panel p-5 sm:p-6">
            <h3 class="mb-4 text-lg font-bold tracking-tight text-ink-text">
                Participants (<span x-text="participants.length"></span>)
            </h3>

            <template x-if="participants.length === 0">
                <p class="text-sm text-ink-subtle">No students have joined yet.</p>
            </template>

            <ul class="divide-y divide-white/10">
                <template x-for="p in participants" :key="p.id">
                    <li class="flex flex-col gap-2 py-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <p class="font-medium text-ink-soft" x-text="p.name"></p>
                            <p class="text-sm text-ink-subtle" x-text="p.email"></p>
                        </div>
                        <div class="w-full text-left sm:w-auto sm:text-right">
                            <span class="text-sm font-semibold text-ink-muted" x-text="p.status"></span>
                            <span class="ml-2 text-sm tabular-nums text-ink-subtle" x-text="'Score: ' + p.score"></span>
                            <template x-if="p.tab_switch_count > 0">
                                <div class="mt-2 flex flex-wrap items-center gap-2 sm:justify-end">
                                    <span class="inline-flex items-center rounded-full border border-rose-300/25 bg-rose-400/10 px-2.5 py-1 text-xs font-semibold text-rose-200"
                                        x-text="'Flagged: ' + p.tab_switch_count + ' tab switch' + (p.tab_switch_count === 1 ? '' : 'es')"></span>
                                    <span class="text-xs tabular-nums text-ink-subtle"
                                        x-text="new Date(p.last_tab_switch_at).toLocaleString()"></span>
                                </div>
                            </template>
                        </div>
                    </li>
                </template>
            </ul>
        </div>

        <form id="end-form" method="POST" action="{{ route('professor.sessions.end', $session->id) }}" class="hidden">
            @csrf
        </form>
    </div>
</x-app-layout>
