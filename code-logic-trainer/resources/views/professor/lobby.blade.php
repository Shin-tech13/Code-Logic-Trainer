<x-app-layout>
    <main class="mx-auto max-w-7xl space-y-5 px-4 py-6 sm:px-6 lg:px-8"
        x-data="{
            status: @js($session->status),
            quizCode: @js($session->quiz_code),
            secondsPerQuestion: @js($session->seconds_per_question),
            analytics: @js($analytics),
            participants: [],
            pagination: { current_page: 1, last_page: 1, per_page: 20, total: 0 },
            search: '',
            statusFilter: 'all',
            activeDimension: 'students',
            lastUpdated: null,
            refreshError: '',
            actionError: '',
            details: null,
            detailsLoading: false,
            detailsError: '',
            participantDetailUrl: @js(route('professor.sessions.participants.show', ['session' => $session->id, 'participant' => 'PARTICIPANT_ID'])),
            _rosterRequest: 0,
            _timer: null,
            chartRows() {
                if (this.activeDimension === 'students') {
                    return this.participants.map(participant => ({
                        id: participant.id,
                        label: participant.name,
                        answered_count: participant.answered_count,
                        remaining_count: participant.unanswered_count,
                        correct_count: participant.correct_count,
                        accuracy_percent: participant.accuracy_percent,
                        total_count: participant.question_count,
                    }));
                }

                return this.analytics[this.activeDimension] || [];
            },
            chartTitle() {
                if (this.activeDimension === 'students') {
                    return 'Question progress by student';
                }

                return this.activeDimension === 'topics'
                    ? 'Average question results by topic'
                    : 'Average question results by difficulty';
            },
            chartSeries() {
                if (this.activeDimension === 'students') {
                    return [
                        { key: 'answered_count', label: 'Answered', color: 'bg-accent-400' },
                        { key: 'remaining_count', label: 'Not answered', color: 'bg-ink-650' },
                    ];
                }

                return [
                    { key: 'average_correct_count', label: 'Avg. correct', color: 'bg-emerald-500' },
                    { key: 'average_incorrect_count', label: 'Avg. incorrect', color: 'bg-rose-400' },
                    { key: 'average_unanswered_count', label: 'Avg. unanswered', color: 'bg-ink-650' },
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
                const qualifier = this.activeDimension === 'students' ? 'questions' : 'questions per participant';
                return `${row.label}: ${series.label}, ${this.formatCount(value)} of ${total} ${qualifier}.`;
            },
            rowSummary(row) {
                if (this.activeDimension === 'students') {
                    return `Answered: ${row.answered_count} of ${row.total_count} · Correct: ${row.correct_count} of ${row.answered_count} · Accuracy: ${row.accuracy_percent}%`;
                }

                return `Answered: ${row.response_count} of ${row.possible_response_count} · Accuracy: ${row.accuracy_percent}%`;
            },
            statusClass(status) {
                return {
                    'Waiting': 'border-amber-300/20 bg-amber-300/[0.06] text-amber-200',
                    'In progress': 'border-accent-300/20 bg-accent-400/[0.06] text-accent-200',
                    'Submitted': 'border-emerald-300/20 bg-emerald-300/[0.06] text-emerald-200',
                    'Disconnected': 'border-rose-300/20 bg-rose-300/[0.06] text-rose-200',
                }[status] || 'border-white/10 bg-white/[0.03] text-ink-muted';
            },
            participantProgress(participant) {
                if (!participant.started) return 'Not started';
                return `${participant.answered_count} of ${participant.question_count} answered`;
            },
            relativeTime(value) {
                if (!value) return 'No activity yet';
                const seconds = Math.max(0, Math.floor((Date.now() - new Date(value).getTime()) / 1000));
                if (seconds < 45) return 'Active just now';
                if (seconds < 90) return 'Active 1 min ago';
                const minutes = Math.floor(seconds / 60);
                if (minutes < 60) return `Active ${minutes} min ago`;
                const hours = Math.floor(minutes / 60);
                if (hours < 24) return `Active ${hours} hr ago`;
                return `Active ${Math.floor(hours / 24)} days ago`;
            },
            async _poll() {
                const rosterRequest = ++this._rosterRequest;
                const params = new URLSearchParams({
                    search: this.search,
                    status: this.statusFilter,
                    page: String(this.pagination.current_page),
                    per_page: String(this.pagination.per_page),
                });

                try {
                    const [summaryResponse, participantsResponse] = await Promise.all([
                        fetch(@js(route('professor.sessions.summary', $session->id)), {
                            headers: { 'Accept': 'application/json' }
                        }),
                        fetch(`${@js(route('professor.sessions.participants', $session->id))}?${params.toString()}`, {
                            headers: { 'Accept': 'application/json' }
                        }),
                    ]);
                    if (!summaryResponse.ok || !participantsResponse.ok) {
                        throw new Error('Could not refresh session monitoring data.');
                    }

                    const [summary, roster] = await Promise.all([
                        summaryResponse.json(),
                        participantsResponse.json(),
                    ]);
                    this.status = summary.status;
                    this.quizCode = summary.quiz_code;
                    this.secondsPerQuestion = summary.seconds_per_question || this.secondsPerQuestion;
                    this.analytics = summary.analytics;
                    if (rosterRequest === this._rosterRequest) {
                        this.participants = roster.data;
                        this.pagination = roster.meta;
                    }
                    this.lastUpdated = summary.refreshed_at || roster.refreshed_at;
                    this.refreshError = '';
                } catch (error) {
                    if (rosterRequest === this._rosterRequest) {
                        this.refreshError = 'Live monitoring could not refresh. The displayed values may be out of date.';
                    }
                }
            },
            async loadParticipants() {
                const rosterRequest = ++this._rosterRequest;
                const params = new URLSearchParams({
                    search: this.search,
                    status: this.statusFilter,
                    page: String(this.pagination.current_page),
                    per_page: String(this.pagination.per_page),
                });

                try {
                    const response = await fetch(`${@js(route('professor.sessions.participants', $session->id))}?${params.toString()}`, {
                        headers: { 'Accept': 'application/json' }
                    });
                    if (!response.ok) throw new Error('Could not refresh the participant list.');
                    const roster = await response.json();
                    if (rosterRequest !== this._rosterRequest) return;
                    this.participants = roster.data;
                    this.pagination = roster.meta;
                    this.lastUpdated = roster.refreshed_at;
                    this.refreshError = '';
                } catch (error) {
                    if (rosterRequest === this._rosterRequest) {
                        this.refreshError = 'The participant list could not refresh. Your current filters are unchanged.';
                    }
                }
            },
            async changePage(page) {
                if (page < 1 || page > this.pagination.last_page) return;
                this.pagination.current_page = page;
                await this.loadParticipants();
            },
            async showWaiting() {
                this.statusFilter = 'waiting';
                this.pagination.current_page = 1;
                await this.loadParticipants();
            },
            async startQuiz() {
                this.actionError = '';
                try {
                    const response = await fetch(@js(route('professor.sessions.start', $session->id)), {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': @js(csrf_token()),
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({}),
                    });
                    const data = await response.json();
                    if (!response.ok || !data.success) {
                        throw new Error(data.message || 'The session could not be started.');
                    }
                    window.location.reload();
                } catch (error) {
                    this.actionError = error.message || 'The session could not be started.';
                }
            },
            async openParticipant(participant) {
                this.details = null;
                this.detailsError = '';
                this.detailsLoading = true;
                this.$refs.participantDialog.showModal();
                try {
                    const url = this.participantDetailUrl.replace('PARTICIPANT_ID', String(participant.id));
                    const response = await fetch(url, { headers: { 'Accept': 'application/json' } });
                    if (!response.ok) throw new Error('Participant details could not be loaded.');
                    this.details = await response.json();
                } catch (error) {
                    this.detailsError = 'Participant details could not be loaded. Close this panel and try again.';
                } finally {
                    this.detailsLoading = false;
                }
            },
            init() {
                this._poll();
                this._timer = setInterval(() => this._poll(), 5000);
            },
            destroy() {
                if (this._timer) clearInterval(this._timer);
            },
            endQuiz() {
                if (window.confirm('End this session? Students who have not submitted will be marked disconnected.')) {
                    document.getElementById('end-form').submit();
                }
            }
        }">

        <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div class="min-w-0">
                <p class="font-mono text-xs tabular-nums text-ink-subtle" x-text="'Session ' + quizCode"></p>
                <h1 class="mt-1 truncate text-2xl font-semibold tracking-tight text-ink-text">{{ $session->quiz->title }}</h1>
                <p class="mt-1 text-sm text-ink-muted">
                    <span x-text="secondsPerQuestion"></span> seconds per question
                    <span class="mx-1 text-ink-faint" aria-hidden="true">·</span>
                    <span x-text="status === 'waiting' ? 'Waiting to start' : (status === 'in_progress' ? 'Live session' : 'Session ended')"></span>
                </p>
                <p class="mt-2 text-xs text-ink-subtle">
                    Roster:
                    @if ($session->roster_mode === 'saved_class')
                        {{ $session->classroom?->title ?? 'Saved class' }}
                    @elseif ($session->roster_mode === 'manual')
                        Manual / specific students
                    @elseif ($session->roster_mode === 'all_whitelisted')
                        All whitelisted students
                    @else
                        Existing whitelist access
                    @endif
                </p>
                @if ($session->is_make_up)
                    <p class="mt-2 inline-flex flex-wrap items-center gap-2 text-sm text-accent-200">
                        <span class="rounded-full border border-accent-300/20 bg-accent-400/[0.06] px-2.5 py-1 text-xs font-semibold">Make-up session</span>
                        @if ($session->make_up_label)
                            <span class="text-ink-muted">{{ $session->make_up_label }}</span>
                        @endif
                    </p>
                @endif
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <p class="text-xs text-ink-subtle" role="status">
                    Last updated
                    <time :datetime="lastUpdated || ''" :title="lastUpdated ? new Date(lastUpdated).toLocaleString() : ''"
                        x-text="lastUpdated ? relativeTime(lastUpdated) : 'Loading'"></time>
                </p>
                <div x-show="status === 'completed'" x-cloak class="flex flex-wrap gap-2">
                    <a href="{{ route('professor.sessions.export', $session->id) }}"
                        class="app-secondary-button inline-flex min-h-11 items-center justify-center px-4 text-sm font-medium">
                        Export session summary
                    </a>
                    <a href="{{ route('professor.analytics.show', $session->quiz_id) }}"
                        class="auth-primary-button inline-flex min-h-11 items-center justify-center px-4 text-sm font-semibold">
                        View quiz analytics
                    </a>
                </div>
            </div>
        </header>

        <p x-show="refreshError" x-cloak x-text="refreshError" class="rounded-[var(--radius-control)] border border-amber-300/20 bg-amber-300/[0.06] px-3 py-2 text-sm text-amber-100" role="status" aria-live="polite"></p>
        <p x-show="actionError" x-cloak x-text="actionError" class="rounded-[var(--radius-control)] border border-rose-300/20 bg-rose-300/[0.06] px-3 py-2 text-sm text-rose-100" role="alert"></p>

        <section class="app-glass-panel overflow-hidden" aria-label="Class summary">
            <dl class="grid grid-cols-2 divide-x divide-y divide-white/10 sm:grid-cols-4 sm:divide-y-0">
                <div class="bg-accent-400/[0.04] px-4 py-4 sm:px-5">
                    <dt class="text-xs font-medium text-ink-subtle">Participants</dt>
                    <dd class="mt-1 font-mono text-2xl font-semibold tabular-nums text-accent-200" x-text="analytics.summary?.participant_count ?? 0"></dd>
                    <p class="mt-1 text-xs text-ink-subtle">Joined this session</p>
                </div>
                <div class="px-4 py-4 sm:px-5">
                    <dt class="text-xs font-medium text-ink-subtle">Completion</dt>
                    <dd class="mt-1 font-mono text-xl font-semibold tabular-nums text-ink-text"
                        x-text="analytics.summary?.participant_count ? analytics.summary.completed_count + ' / ' + analytics.summary.participant_count : '—'"></dd>
                    <p class="mt-1 text-xs text-ink-subtle"
                        x-text="analytics.summary?.participant_count ? analytics.summary.completion_rate_percent + '% submitted' : 'No participants yet'"></p>
                </div>
                <div class="px-4 py-4 sm:px-5">
                    <dt class="text-xs font-medium text-ink-subtle">Class accuracy</dt>
                    <dd class="mt-1 font-mono text-xl font-semibold tabular-nums text-ink-text"
                        x-text="analytics.summary?.answered_count ? analytics.summary.accuracy_percent + '%' : '—'"></dd>
                    <p class="mt-1 text-xs text-ink-subtle"
                        x-text="'Correct: ' + (analytics.summary?.correct_count ?? 0) + ' of ' + (analytics.summary?.answered_count ?? 0) + ' answered'"></p>
                </div>
                <div class="px-4 py-4 sm:px-5">
                    <dt class="text-xs font-medium text-ink-subtle">Questions to review</dt>
                    <dd class="mt-1 font-mono text-xl font-semibold tabular-nums text-amber-200" x-text="analytics.summary?.questions_needing_review ?? 0"></dd>
                    <p class="mt-1 text-xs text-ink-subtle">Below 60% accuracy with responses</p>
                </div>
            </dl>
            <p class="flex flex-wrap gap-x-2 gap-y-1 border-t border-white/10 px-4 py-2 text-xs leading-5 text-ink-subtle sm:px-5">
                <span x-text="'Answered: ' + (analytics.summary?.answered_count ?? 0) + ' of ' + (analytics.summary?.possible_response_count ?? 0) + ' possible'"></span>
                <span aria-hidden="true">·</span>
                <span x-text="'Correct: ' + (analytics.summary?.correct_count ?? 0) + ' of ' + (analytics.summary?.answered_count ?? 0) + ' answered'"></span>
                <span aria-hidden="true">·</span>
                <span x-text="'Accuracy: ' + (analytics.summary?.answered_count ? analytics.summary.accuracy_percent + '%' : '—')"></span>
                <span>Accuracy excludes unanswered questions; blanks are not counted as incorrect.</span>
            </p>
        </section>

        @if ($session->status === 'waiting')
            <section x-show="status === 'waiting'" x-cloak class="app-glass-panel p-4 sm:p-5" aria-labelledby="session-timer-heading">
                <div class="mb-3">
                    <h2 id="session-timer-heading" class="text-base font-semibold tracking-tight text-ink-text">Session timer</h2>
                    <p class="mt-1 text-sm leading-5 text-ink-subtle">Adjust the time allowed for each question before the exam starts.</p>
                </div>
                <form method="POST" action="{{ route('professor.sessions.timer.update', $session->id) }}"
                    x-data
                    @submit="$refs.secondsPerQuestion.value = window.normalizeQuestionTimer($refs.secondsPerQuestion.value, true)">
                    @csrf
                    @method('PATCH')
                    <label for="session-seconds-per-question" class="auth-label">Seconds per question</label>
                    <div class="mt-2 flex flex-col gap-3 sm:flex-row sm:items-start">
                        <div class="w-full sm:max-w-xs">
                            <input id="session-seconds-per-question" x-ref="secondsPerQuestion" name="seconds_per_question" type="number" min="0" max="100" step="1"
                                value="{{ old('seconds_per_question', $session->seconds_per_question) }}" placeholder="20 (default)"
                                class="min-h-11 w-full rounded-[var(--radius-control)] border border-white/15 bg-ink-900/80 px-3 py-2 text-ink-text placeholder:text-ink-subtle focus:border-accent-400 focus:outline-none focus:ring-2 focus:ring-accent-400/30"
                                @input="$event.target.value = window.normalizeQuestionTimer($event.target.value)"
                                @blur="$event.target.value = window.normalizeQuestionTimer($event.target.value, true)">
                            <p class="mt-2 text-xs leading-5 text-ink-subtle">1–100s (leave blank or 0 for default 20s; values above 100 cap at 100s)</p>
                            @error('seconds_per_question')
                                <p class="auth-input-error mt-2 text-sm" role="alert">{{ $message }}</p>
                            @enderror
                        </div>
                        <button type="submit" class="auth-primary-button inline-flex min-h-11 shrink-0 items-center justify-center whitespace-nowrap px-5 text-sm font-semibold">Save timer</button>
                    </div>
                </form>
            </section>
        @endif

        <section class="app-glass-panel flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between sm:p-5" aria-label="Session controls">
            <div>
                <p class="text-sm font-medium text-ink-soft" x-text="status === 'waiting' ? 'Ready when your class is set.' : (status === 'in_progress' ? 'Students can join while the session is live.' : 'This session is closed.')"></p>
                <p class="mt-1 text-xs text-ink-subtle">Students who join after the start remain marked as not started until they open the quiz.</p>
            </div>
            <template x-if="status === 'waiting'">
                <button type="button" @click="startQuiz"
                    class="inline-flex min-h-11 shrink-0 items-center justify-center rounded-[var(--radius-control)] bg-emerald-700 px-5 text-sm font-semibold text-white transition-colors duration-base hover:bg-emerald-600 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-200">
                    Start quiz
                </button>
            </template>
            <template x-if="status === 'in_progress'">
                <button type="button" @click="endQuiz"
                    class="app-secondary-button inline-flex min-h-11 shrink-0 items-center justify-center px-5 text-sm font-semibold">
                    End session
                </button>
            </template>
        </section>

        <section class="app-glass-panel p-4 sm:p-5" aria-labelledby="live-analytics-heading">
            <header class="flex flex-col gap-4 border-b border-white/10 pb-4">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h2 id="live-analytics-heading" class="text-lg font-semibold tracking-tight text-ink-text">Live class analytics</h2>
                        <p class="mt-1 text-sm leading-5 text-ink-subtle">Compare current responses by student, topic, or difficulty. This view is private to the teaching team; it does not rank students.</p>
                    </div>
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
                </div>
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h3 class="text-sm font-medium text-ink-soft" x-text="chartTitle()"></h3>
                    <p class="text-xs tabular-nums text-ink-subtle" x-text="'Scale: 0 to ' + chartMaximum() + ' questions'"></p>
                </div>
            </header>

            <p x-show="activeDimension === 'students' && pagination.total === 1" x-cloak class="mt-3 text-sm text-ink-muted">
                One participant so far. Progress is shown for monitoring, not leaderboard ranking.
            </p>
            <p x-show="activeDimension === 'students' && pagination.total === 0" x-cloak class="mt-4 rounded-[var(--radius-control)] border border-white/10 bg-ink-950/40 px-4 py-6 text-center text-sm text-ink-subtle">
                No participants match this view yet. Progress will appear here after students join.
            </p>
            <p x-show="activeDimension !== 'students' && analytics.summary?.answered_count === 0" x-cloak class="mt-4 rounded-[var(--radius-control)] border border-white/10 bg-ink-950/40 px-4 py-6 text-center text-sm text-ink-subtle">
                No responses yet. Topic and difficulty breakdowns will appear as students answer.
            </p>

            <div x-show="chartRows().length > 0 && (activeDimension === 'students' || analytics.summary?.answered_count > 0)" x-cloak class="mt-4">
                <div class="mb-3 flex flex-wrap gap-x-5 gap-y-2" role="group" aria-label="Chart legend">
                    <template x-for="series in chartSeries()" :key="series.key">
                        <span class="inline-flex items-center gap-2 text-xs text-ink-muted">
                            <span class="h-2.5 w-2.5 rounded-sm" :class="series.color" aria-hidden="true"></span>
                            <span x-text="series.label"></span>
                        </span>
                    </template>
                </div>
                <div class="space-y-3">
                    <template x-for="row in chartRows()" :key="row.id || row.label">
                        <article class="rounded-[var(--radius-control)] border border-white/[0.08] bg-ink-950/30 p-3 sm:px-4">
                            <div class="mb-3 flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                                <h4 class="min-w-0 break-words text-sm font-medium text-ink-soft" x-text="row.label"></h4>
                                <p class="text-right text-xs tabular-nums text-ink-subtle" x-text="rowSummary(row)"></p>
                            </div>
                            <div class="space-y-2" role="group" :aria-label="row.label + ' question results'">
                                <template x-for="series in chartSeries()" :key="series.key">
                                    <div class="grid grid-cols-[7rem_minmax(0,1fr)_2.5rem] items-center gap-2 sm:grid-cols-[9rem_minmax(0,1fr)_3rem]">
                                        <span class="truncate text-xs text-ink-muted" x-text="series.label"></span>
                                        <div class="h-2 overflow-hidden rounded-full bg-white/[0.07]" role="img"
                                            :aria-label="seriesTooltip(row, series)" :title="seriesTooltip(row, series)">
                                            <div class="h-full rounded-full transition-[width] duration-quick ease-craft-out motion-reduce:transition-none" :class="series.color"
                                                :style="'width: ' + seriesPercent(row, series.key) + '%'"></div>
                                        </div>
                                        <span class="text-right font-mono text-xs tabular-nums text-ink-soft" x-text="formatCount(seriesValue(row, series.key))"></span>
                                    </div>
                                </template>
                            </div>
                        </article>
                    </template>
                </div>
            </div>
        </section>

        <section class="app-glass-panel p-4 sm:p-5" aria-labelledby="question-breakdown-heading">
            <div class="flex flex-col gap-1">
                <h2 id="question-breakdown-heading" class="text-lg font-semibold tracking-tight text-ink-text">Question breakdown</h2>
                <p class="text-sm text-ink-subtle">Accuracy is correct responses divided by answered responses. Blank answers are shown separately as unanswered.</p>
            </div>
            <p x-show="analytics.questions?.length === 0" x-cloak class="mt-4 rounded-[var(--radius-control)] border border-white/10 bg-ink-950/40 px-4 py-6 text-center text-sm text-ink-subtle">
                No questions are available for this session.
            </p>
            <div x-show="analytics.questions?.length > 0" x-cloak class="mt-4 overflow-x-auto rounded-[var(--radius-control)] border border-white/10 focus-visible:outline focus-visible:outline-2 focus-visible:outline-accent-400"
                role="region" aria-label="Question response breakdown table" tabindex="0">
                <table class="w-full min-w-[760px] border-collapse text-left text-sm">
                    <thead class="bg-white/[0.03] text-xs font-medium text-ink-subtle">
                        <tr>
                            <th scope="col" class="px-3 py-3">Question</th>
                            <th scope="col" class="px-3 py-3 text-right">Responses</th>
                            <th scope="col" class="px-3 py-3 text-right">Correct</th>
                            <th scope="col" class="px-3 py-3 text-right">Incorrect</th>
                            <th scope="col" class="px-3 py-3 text-right">Unanswered</th>
                            <th scope="col" class="px-3 py-3 text-right">Accuracy</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/[0.07]">
                        <template x-for="question in analytics.questions" :key="question.id">
                            <tr class="align-top">
                                <th scope="row" class="max-w-md px-3 py-3 font-medium text-ink-soft">
                                    <span class="mr-2 font-mono text-xs text-ink-subtle" x-text="'Q' + question.number"></span>
                                    <span class="line-clamp-2" x-text="question.question"></span>
                                </th>
                                <td class="whitespace-nowrap px-3 py-3 text-right font-mono text-xs tabular-nums text-ink-soft"
                                    x-text="question.response_count + ' / ' + question.participant_count"></td>
                                <td class="px-3 py-3 text-right font-mono text-xs tabular-nums text-emerald-200" x-text="question.correct_count"></td>
                                <td class="px-3 py-3 text-right font-mono text-xs tabular-nums text-rose-200" x-text="question.incorrect_count"></td>
                                <td class="px-3 py-3 text-right font-mono text-xs tabular-nums text-ink-muted" x-text="question.unanswered_count"></td>
                                <td class="px-3 py-3 text-right font-mono text-xs tabular-nums"
                                    :class="question.response_count > 0 && question.accuracy_percent < 60 ? 'text-amber-200' : 'text-ink-soft'"
                                    x-text="question.response_count ? question.accuracy_percent + '%' : '—'"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
            <p class="mt-2 text-xs text-ink-faint sm:hidden">Scroll horizontally to see all question metrics.</p>
        </section>

        <section class="app-glass-panel p-4 sm:p-5" aria-labelledby="participants-heading">
            <header class="flex flex-col gap-3 border-b border-white/10 pb-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h2 id="participants-heading" class="text-lg font-semibold tracking-tight text-ink-text">Participants</h2>
                    <p class="mt-1 text-sm text-ink-subtle"
                        x-text="pagination.total + (statusFilter === 'all' && !search ? ' participants joined' : ' participants match this filter') + ' · sorted by name'"></p>
                </div>
                <button type="button" @click="showWaiting"
                    class="app-action-button inline-flex min-h-11 items-center justify-center self-start px-3 text-sm font-medium sm:self-auto">
                    Show who hasn’t started
                </button>
            </header>

            <div class="mt-4 flex flex-col gap-3 sm:flex-row">
                <div class="min-w-0 flex-1">
                    <label for="participant-search" class="sr-only">Search participants by name or email</label>
                    <input id="participant-search" type="search" autocomplete="off" placeholder="Search name or email"
                        x-model="search" @input.debounce.300ms="pagination.current_page = 1; loadParticipants()"
                        class="min-h-11 w-full rounded-[var(--radius-field)] border border-white/15 bg-ink-900/80 px-3 py-2 text-base text-ink-text placeholder:text-ink-subtle focus:border-accent-400 focus:outline-none focus:ring-2 focus:ring-accent-400/30 sm:text-sm">
                </div>
                <div class="sm:w-52">
                    <label for="participant-status" class="sr-only">Filter participants by status</label>
                    <select id="participant-status" x-model="statusFilter" @change="pagination.current_page = 1; loadParticipants()"
                        class="app-action-select min-h-11 w-full text-base focus-visible:outline focus-visible:outline-2 focus-visible:outline-accent-400 sm:text-sm">
                        <option value="all">All statuses</option>
                        <option value="waiting">Waiting</option>
                        <option value="in_progress">In progress</option>
                        <option value="submitted">Submitted</option>
                        <option value="disconnected">Disconnected</option>
                    </select>
                </div>
            </div>
            <p class="mt-2 text-xs text-ink-faint">A participant is marked disconnected after two minutes without activity. Opening the quiz again refreshes activity.</p>

            <div class="mt-4 overflow-x-auto rounded-[var(--radius-control)] border border-white/10 focus-visible:outline focus-visible:outline-2 focus-visible:outline-accent-400"
                role="region" aria-label="Participant roster table" tabindex="0">
                <table class="w-full min-w-[900px] border-collapse text-left text-sm">
                    <thead class="bg-white/[0.03] text-xs font-medium text-ink-subtle">
                        <tr>
                            <th scope="col" class="px-3 py-3">Student</th>
                            <th scope="col" class="px-3 py-3">Status</th>
                            <th scope="col" class="px-3 py-3">Progress</th>
                            <th scope="col" class="px-3 py-3 text-right">Score</th>
                            <th scope="col" class="px-3 py-3">Last activity</th>
                            <th scope="col" class="px-3 py-3">Monitoring signal</th>
                            <th scope="col" class="px-3 py-3 text-right">Details</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/[0.07]">
                        <template x-for="participant in participants" :key="participant.id">
                            <tr class="transition-colors duration-quick hover:bg-white/[0.025]">
                                <th scope="row" class="max-w-56 truncate px-3 py-3 font-medium text-ink-soft" x-text="participant.name"></th>
                                <td class="px-3 py-3">
                                    <span class="inline-flex min-h-7 items-center rounded-[var(--radius-field)] border px-2.5 text-xs font-medium"
                                        :class="statusClass(participant.status)" x-text="participant.status"></span>
                                </td>
                                <td class="whitespace-nowrap px-3 py-3 text-ink-muted" x-text="participantProgress(participant)"></td>
                                <td class="whitespace-nowrap px-3 py-3 text-right font-mono text-xs tabular-nums text-ink-soft"
                                    x-text="participant.score_label || 'Not submitted'"></td>
                                <td class="whitespace-nowrap px-3 py-3 text-ink-muted">
                                    <time :datetime="participant.last_activity_at || ''"
                                        :title="participant.last_activity_at ? new Date(participant.last_activity_at).toLocaleString() : 'No activity recorded'"
                                        x-text="relativeTime(participant.last_activity_at)"></time>
                                </td>
                                <td class="whitespace-nowrap px-3 py-3 text-xs text-ink-subtle">
                                    <span x-show="participant.tab_switch_count > 0" x-cloak
                                        :title="'Review signal only, not proof of misconduct. Last recorded: ' + (participant.last_tab_switch_at ? new Date(participant.last_tab_switch_at).toLocaleString() : 'not available')"
                                        x-text="participant.tab_switch_count + ' tab switch' + (participant.tab_switch_count === 1 ? '' : 'es')"></span>
                                    <span x-show="participant.tab_switch_count === 0">—</span>
                                </td>
                                <td class="px-3 py-2 text-right">
                                    <button type="button" @click="openParticipant(participant)"
                                        class="app-secondary-button inline-flex min-h-10 items-center justify-center px-3 text-xs font-medium focus-visible:outline focus-visible:outline-2 focus-visible:outline-accent-400">
                                        Inspect progress
                                    </button>
                                </td>
                            </tr>
                        </template>
                        <tr x-show="participants.length === 0" x-cloak>
                            <td colspan="7" class="px-4 py-8 text-center text-sm text-ink-subtle">
                                No participants match these filters. Try another status or search term.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p class="mt-2 text-xs text-ink-faint sm:hidden">Scroll horizontally to see activity and inspection actions.</p>

            <footer class="mt-4 flex flex-col gap-3 border-t border-white/10 pt-4 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-xs tabular-nums text-ink-subtle" x-text="'Page ' + pagination.current_page + ' of ' + pagination.last_page + ' · ' + pagination.total + ' participants'"></p>
                <nav class="flex items-center gap-2" aria-label="Participant pages">
                    <button type="button" @click="changePage(pagination.current_page - 1)" :disabled="pagination.current_page <= 1"
                        class="app-secondary-button min-h-11 px-3 text-sm disabled:cursor-not-allowed disabled:opacity-40">Previous</button>
                    <button type="button" @click="changePage(pagination.current_page + 1)" :disabled="pagination.current_page >= pagination.last_page"
                        class="app-secondary-button min-h-11 px-3 text-sm disabled:cursor-not-allowed disabled:opacity-40">Next</button>
                </nav>
            </footer>
        </section>

        <dialog x-ref="participantDialog" @close="details = null"
            class="m-auto max-h-[85dvh] w-[calc(100%-2rem)] max-w-2xl overflow-y-auto rounded-[var(--radius-modal)] border border-white/15 bg-ink-850 p-0 text-ink-text shadow-2xl backdrop:bg-black/70">
            <div class="sticky top-0 z-10 flex items-start justify-between gap-4 border-b border-white/10 bg-ink-850/95 px-4 py-4 backdrop-blur sm:px-5">
                <div>
                    <h2 class="text-lg font-semibold tracking-tight" id="participant-details-heading"
                        x-text="details?.participant?.name || 'Participant progress'"></h2>
                    <p class="mt-1 text-sm text-ink-subtle">Saved response status only. Answer contents are not shown.</p>
                </div>
                <button type="button" @click="$refs.participantDialog.close()" aria-label="Close participant details"
                    class="app-secondary-button inline-flex min-h-11 min-w-11 items-center justify-center text-lg focus-visible:outline focus-visible:outline-2 focus-visible:outline-accent-400">
                    <span aria-hidden="true">×</span>
                </button>
            </div>
            <div class="space-y-4 p-4 sm:p-5" aria-labelledby="participant-details-heading">
                <p x-show="detailsLoading" class="text-sm text-ink-muted" role="status">Loading participant progress…</p>
                <p x-show="detailsError" x-cloak x-text="detailsError" class="text-sm text-rose-200" role="alert"></p>
                <template x-if="details?.participant">
                    <div class="space-y-4">
                        <dl class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                            <div class="app-tile p-3">
                                <dt class="text-xs text-ink-subtle">Status</dt>
                                <dd class="mt-1 text-sm font-medium" x-text="details.participant.status"></dd>
                            </div>
                            <div class="app-tile p-3">
                                <dt class="text-xs text-ink-subtle">Answered</dt>
                                <dd class="mt-1 font-mono text-sm tabular-nums" x-text="details.participant.answered_count + ' of ' + details.participant.question_count"></dd>
                            </div>
                            <div class="app-tile p-3">
                                <dt class="text-xs text-ink-subtle">Correct</dt>
                                <dd class="mt-1 font-mono text-sm tabular-nums" x-text="details.participant.correct_count + ' of ' + details.participant.answered_count + ' answered'"></dd>
                            </div>
                            <div class="app-tile p-3">
                                <dt class="text-xs text-ink-subtle">Accuracy</dt>
                                <dd class="mt-1 font-mono text-sm tabular-nums" x-text="details.participant.answered_count ? details.participant.accuracy_percent + '%' : '—'"></dd>
                            </div>
                            <div class="app-tile p-3">
                                <dt class="text-xs text-ink-subtle">Score</dt>
                                <dd class="mt-1 font-mono text-sm tabular-nums" x-text="details.participant.score_label || 'Not submitted'"></dd>
                            </div>
                        </dl>
                        <dl class="grid gap-2 text-sm sm:grid-cols-2">
                            <div><dt class="inline text-ink-subtle">Joined: </dt><dd class="inline text-ink-muted" x-text="details.participant.joined_at ? new Date(details.participant.joined_at).toLocaleString() : 'Not recorded'"></dd></div>
                            <div><dt class="inline text-ink-subtle">Started: </dt><dd class="inline text-ink-muted" x-text="details.participant.started_at ? new Date(details.participant.started_at).toLocaleString() : 'Not started'"></dd></div>
                            <div><dt class="inline text-ink-subtle">Last active: </dt><dd class="inline text-ink-muted" x-text="details.participant.last_activity_at ? new Date(details.participant.last_activity_at).toLocaleString() : 'No activity'"></dd></div>
                            <div><dt class="inline text-ink-subtle">Submitted: </dt><dd class="inline text-ink-muted" x-text="details.participant.submitted_at ? new Date(details.participant.submitted_at).toLocaleString() : 'Not submitted'"></dd></div>
                        </dl>
                        <p x-show="details.participant.tab_switch_count > 0" x-cloak class="text-xs text-ink-subtle"
                            x-text="details.participant.tab_switch_count + ' tab switch' + (details.participant.tab_switch_count === 1 ? '' : 'es') + ' recorded. This is a review signal, not proof of misconduct.'"></p>
                        <div class="overflow-x-auto rounded-[var(--radius-control)] border border-white/10">
                            <table class="w-full min-w-[26rem] text-left text-sm">
                                <thead class="bg-white/[0.03] text-xs text-ink-subtle">
                                    <tr>
                                        <th scope="col" class="px-3 py-2.5">Question</th>
                                        <th scope="col" class="px-3 py-2.5">Saved status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-white/[0.07]">
                                    <template x-for="question in details.questions" :key="question.number">
                                        <tr>
                                            <th scope="row" class="px-3 py-2.5 font-medium text-ink-soft">
                                                <span class="mr-2 font-mono text-xs text-ink-subtle" x-text="'Q' + question.number"></span>
                                                <span class="line-clamp-2" x-text="question.question"></span>
                                            </th>
                                            <td class="whitespace-nowrap px-3 py-2.5 text-xs"
                                                :class="question.status === 'Correct' ? 'text-emerald-200' : (question.status === 'Incorrect' ? 'text-rose-200' : 'text-ink-muted')"
                                                x-text="question.status"></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </template>
            </div>
        </dialog>

        <form id="end-form" method="POST" action="{{ route('professor.sessions.end', $session->id) }}" class="hidden">
            @csrf
        </form>
    </main>
</x-app-layout>
