<x-app-layout>
    <div class="auth-shell relative isolate min-h-[calc(100vh-4rem)] overflow-hidden px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
        <div class="ambient-orb ambient-orb--violet" aria-hidden="true"></div>
        <div class="ambient-orb ambient-orb--indigo" aria-hidden="true"></div>
        <div class="ambient-orb ambient-orb--plum" aria-hidden="true"></div>

        <main class="relative z-10 mx-auto max-w-5xl space-y-7">
            <header>
                <p class="text-xs font-semibold tracking-wide text-accent-300">Professor workspace</p>
                <h1 class="mt-2 text-3xl font-bold tracking-tight text-ink-text sm:text-4xl">Quiz dashboard</h1>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-ink-subtle">
                    Turn course material into a quiz and manage your existing quizzes from one place.
                </p>
            </header>

            @if (session('status'))
                <div class="rounded-[var(--radius-control)] border border-emerald-300/20 bg-emerald-400/10 px-4 py-3 text-sm leading-5 text-emerald-100"
                    role="status" aria-live="polite">
                    {{ session('status') }}
                </div>
            @endif

            <section class="app-glass-panel p-5 sm:p-7 lg:p-8" aria-labelledby="create-quiz-heading"
                x-data="{
                    title: '',
                    description: '',
                    count: '',
                    quizFormat: 'multiple_choice',
                    fileName: '',
                    fileSize: '',
                    isGenerating: false,
                    errorMessage: '',
                    progress: 0,
                    statusMessage: '',
                    _timer: null,
                
                    startProgress() {
                        this.progress = 3;
                        this.statusMessage = 'Uploading PDF & extracting text...';
                        this._timer = setInterval(() => {
                            if (this.progress < 98) {
                                const remaining = 98 - this.progress;
                                this.progress = Math.min(98, this.progress + Math.max(0.2, remaining * 0.05));
                            }
                            if (this.progress >= 80) {
                                this.statusMessage = 'Generating questions with the configured AI provider...';
                            } else if (this.progress >= 40) {
                                this.statusMessage = 'Analyzing the PDF content...';
                            }
                        }, 400);
                    },
                
                    stopProgress() {
                        if (this._timer) {
                            clearInterval(this._timer);
                            this._timer = null;
                        }
                    },
                
                    selectPdf(event) {
                        const file = event.target.files[0];
                        this.fileName = file ? file.name : '';
                        this.fileSize = file ? (file.size < 1048576 ?
                            `${Math.max(1, Math.round(file.size / 1024))} KB` :
                            `${(file.size / 1048576).toFixed(1)} MB`) : '';
                        this.errorMessage = '';
                    },
                
                    async generateQuiz() {
                        if (!this.title.trim() || this.isGenerating) return;
                
                        const fileInput = this.$refs.pdf;
                        if (!fileInput.files.length) {
                            this.errorMessage = 'Please select a PDF file.';
                            return;
                        }
                
                        this.isGenerating = true;
                        this.errorMessage = '';
                        this.progress = 0;
                        this.startProgress();
                
                        try {
                            const formData = new FormData();
                            formData.append('pdf', fileInput.files[0]);
                            formData.append('title', this.title.trim());
                            formData.append('description', this.description.trim());
                            formData.append('count', this.count || 0);
                            formData.append('seconds_per_question', window.normalizeQuestionTimer(this.$refs.secondsPerQuestion.value, true));
                            formData.append('quiz_format', this.quizFormat);
                            formData.append('idempotency_key', @js($generationRequestId));
                            formData.append('_token', '{{ csrf_token() }}');
                
                            const response = await fetch('{{ route('professor.quizzes.generate') }}', {
                                method: 'POST',
                                headers: { 'Accept': 'application/json' },
                                body: formData,
                            });
                
                            const data = await response.json();
                            if (!response.ok || !data.success) {
                                const validationMessage = data.errors ? Object.values(data.errors).flat()[0] : null;
                                throw new Error(validationMessage || data.message || `Request failed (${response.status})`);
                            }
                
                            this.stopProgress();
                            this.progress = 100;
                            this.statusMessage = `${data.question_count} questions generated. Opening your quiz for review...`;
                            await new Promise(resolve => setTimeout(resolve, 700));
                            window.location.href = data.redirect_url;
                        } catch (err) {
                            this.stopProgress();
                            this.errorMessage = err.message || 'Something went wrong.';
                            this.isGenerating = false;
                            this.progress = 0;
                        }
                    }
                }">
                <div class="mb-6 flex items-start gap-4">
                    <span
                        class="flex h-11 w-11 shrink-0 items-center justify-center rounded-[var(--radius-control)] border border-accent-300/20 bg-accent-400/10 text-accent-200"
                        aria-hidden="true">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M12 16V4m0 0L7 9m5-5 5 5M5 14v4a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-4" />
                        </svg>
                    </span>
                    <div>
                        <h2 id="create-quiz-heading" class="text-xl font-bold tracking-tight text-ink-text">Create quiz
                            from PDF</h2>
                        <p class="mt-1 text-sm leading-6 text-ink-subtle">Upload course material to create
                            multiple-choice or fill-in-the-blank questions grounded in the document.</p>
                    </div>
                </div>

                <form @submit.prevent="generateQuiz" class="grid gap-5 sm:grid-cols-2" x-show="!isGenerating">
                    <div class="sm:col-span-2">
                        <label for="quiz-title" class="auth-label">Quiz title</label>
                        <input id="quiz-title" type="text" x-model="title" required maxlength="255"
                            class="auth-input mt-2" placeholder="e.g. Data Structures Midterm">
                    </div>

                    <div class="sm:col-span-2">
                        <label for="quiz-description" class="auth-label">Description <span
                                class="font-normal text-ink-subtle">(optional)</span></label>
                        <input id="quiz-description" type="text" x-model="description" maxlength="1000"
                            class="auth-input mt-2" placeholder="Add a short note about this quiz">
                    </div>

                    <div>
                        <label for="quiz-count" class="auth-label">Number of questions <span
                                class="font-normal text-ink-subtle">(optional)</span></label>
                        <input id="quiz-count" type="number" x-model="count" min="0" max="40"
                            class="auth-input mt-2" placeholder="Automatic">
                        <p class="mt-2 text-xs leading-5 text-ink-subtle">Leave blank or enter 0 for automatic count.
                            Positive values are capped at 150.</p>
                    </div>

                    <div>
                        <label for="quiz-seconds-per-question" class="auth-label">Seconds per question</label>
                        <input id="quiz-seconds-per-question" x-ref="secondsPerQuestion" name="seconds_per_question"
                            type="number" min="0" max="100" step="1" value="20"
                            placeholder="20 (default)" class="auth-input mt-2"
                            @input="$event.target.value = window.normalizeQuestionTimer($event.target.value)"
                            @blur="$event.target.value = window.normalizeQuestionTimer($event.target.value, true)">
                        <p class="mt-2 text-xs leading-5 text-ink-subtle">min 1second, msximum of 100seconds</p>
                    </div>

                    <div>
                        <label for="quiz-format" class="auth-label">Question format</label>
                        <select id="quiz-format" x-model="quizFormat" class="auth-input mt-2">
                            <option value="multiple_choice">Multiple choice</option>
                            <option value="fill_in_the_blank">Fill in the blank</option>
                        </select>
                        <p class="mt-2 text-xs leading-5 text-ink-subtle"
                            x-text="quizFormat === 'multiple_choice' ? ' ' : 'Students enter a short answer; equivalent answers may be accepted.'">
                        </p>
                    </div>

                    <div>
                        <label for="quiz-pdf" class="auth-label">PDF file</label>
                        <input id="quiz-pdf" type="file" x-ref="pdf" accept="application/pdf" required
                            @change="selectPdf($event)" class="professor-file-input mt-2 block w-full"
                            aria-describedby="pdf-guidance">
                        <p id="pdf-guidance" class="mt-2 text-xs leading-5 text-ink-subtle">Guidelines: -PDF only,
                            -Maximum 20 MB. Text extraction is local when possible; if the parser cannot read the PDF,
                            Gemini may receive it for extraction. Extracted text is normally sent to Gemini and may also
                            be sent to Groq if Gemini is temporarily unavailable. Review each provider's current
                            data-handling terms before uploading sensitive material.</p>
                        <p x-show="fileName" x-cloak class="mt-2 break-all text-xs text-accent-200"
                            aria-live="polite">
                            <span class="font-semibold">Selected:</span> <span x-text="fileName"></span>
                            <span class="text-ink-subtle" x-text="fileSize ? ` · ${fileSize}` : ''"></span>
                        </p>
                    </div>

                    <div class="sm:col-span-2">
                        <button type="submit"
                            class="auth-primary-button inline-flex min-h-12 w-full items-center justify-center gap-2 px-5 text-sm font-semibold"
                            :disabled="isGenerating">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M12 3v3m0 12v3m9-9h-3M6 12H3m15.36-6.36-2.12 2.12M7.76 16.24l-2.12 2.12m12.72 0-2.12-2.12M7.76 7.76 5.64 5.64" />
                            </svg>
                            Generate quiz
                        </button>
                    </div>

                    <p x-show="errorMessage" x-text="errorMessage" x-cloak
                        class="rounded-[var(--radius-control)] border border-rose-300/25 bg-rose-400/10 px-4 py-3 text-sm leading-5 text-rose-100 sm:col-span-2"
                        role="alert"></p>
                </form>

                <div x-show="isGenerating" x-cloak class="mx-auto max-w-xl space-y-5 py-5 text-center" role="status"
                    aria-live="polite">
                    <p class="text-lg font-semibold text-accent-200" x-text="statusMessage"></p>
                    <div class="h-3 overflow-hidden rounded-full bg-white/10" role="progressbar"
                        aria-label="Quiz generation progress" aria-valuemin="0" aria-valuemax="100"
                        :aria-valuenow="Math.round(progress)">
                        <div class="h-full rounded-full bg-gradient-to-r from-accent-600 to-accent-400 transition-[width] duration-700 ease-out"
                            :style="'width: ' + Math.round(progress) + '%'"></div>
                    </div>
                    <p class="font-mono text-xs tabular-nums text-ink-subtle"
                        x-text="Math.round(progress) + '% completed'"></p>
                </div>
            </section>

            <section class="app-glass-panel overflow-hidden" aria-labelledby="your-quizzes-heading">
                <header
                    class="flex flex-col gap-1 border-b border-white/10 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                    <div>
                        <h2 id="your-quizzes-heading" class="text-lg font-semibold tracking-tight text-ink-text">Your
                            quizzes</h2>
                        <p class="mt-1 text-sm text-ink-subtle">Quizzes created from your course materials.</p>
                    </div>
                    <span class="text-sm text-ink-muted">{{ $quizzes->count() }}
                        {{ $quizzes->count() === 1 ? 'quiz' : 'quizzes' }}</span>
                </header>

                @if ($quizzes->isEmpty())
                    <div class="px-6 py-10 text-center">
                        <h3 class="font-semibold text-ink-text">No quizzes yet</h3>
                        <p class="mt-2 text-sm text-ink-subtle">Start by uploading a PDF in the quiz creation form
                            above.</p>
                        <a href="#create-quiz-heading"
                            class="auth-link mt-4 inline-flex min-h-11 items-center rounded-[var(--radius-field)] text-sm font-semibold focus-visible:outline focus-visible:outline-2 focus-visible:outline-accent-400">
                            Go to PDF upload
                        </a>
                    </div>
                @else
                    <ul class="divide-y divide-white/[0.08]">
                        @foreach ($quizzes as $quiz)
                            <li
                                class="flex flex-col gap-4 px-5 py-5 transition-colors hover:bg-white/[0.025] sm:flex-row sm:items-center sm:justify-between sm:px-6">
                                <div class="min-w-0">
                                    <a href="{{ route('professor.quizzes.show', $quiz->id) }}"
                                        class="rounded-sm text-base font-semibold text-accent-200 transition-colors duration-quick hover:text-ink-text focus-visible:outline focus-visible:outline-2 focus-visible:outline-accent-400">
                                        {{ $quiz->title }}
                                    </a>
                                    <p class="mt-1 text-sm text-ink-subtle">
                                        {{ $quiz->questions_count }}
                                        {{ $quiz->questions_count === 1 ? 'question' : 'questions' }}
                                        <span aria-hidden="true">·</span>
                                        {{ $quiz->seconds_per_question }}s per question
                                        <span aria-hidden="true">·</span>
                                        Created {{ $quiz->created_at->diffForHumans() }}
                                    </p>
                                </div>
                                <div class="flex flex-wrap items-center gap-3">
                                    <a href="{{ route('professor.sessions.create', $quiz->id) }}"
                                        class="app-secondary-button inline-flex min-h-11 items-center justify-center px-4 text-sm font-semibold">
                                        Start live session
                                    </a>
                                    <a href="{{ route('professor.analytics.show', $quiz->id) }}"
                                        class="app-secondary-button inline-flex min-h-10 items-center justify-center px-4 text-sm font-semibold">
                                        View analytics
                                    </a>
                                    <form method="POST" action="{{ route('professor.quizzes.destroy', $quiz->id) }}"
                                        onsubmit="return confirm('Delete this quiz?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                            class="app-action-button min-h-11 rounded-[var(--radius-control)] border border-rose-300/20 px-4 text-sm text-rose-200 hover:bg-rose-300/10 hover:text-white">
                                            Delete
                                        </button>
                                    </form>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </main>
    </div>
</x-app-layout>
