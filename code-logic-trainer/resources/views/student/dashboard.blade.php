<x-app-layout>
    <div class="auth-shell relative isolate min-h-[calc(100vh-4rem)] overflow-hidden px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
        <div class="ambient-orb ambient-orb--violet" aria-hidden="true"></div>
        <div class="ambient-orb ambient-orb--indigo" aria-hidden="true"></div>
        <div class="ambient-orb ambient-orb--cyan" aria-hidden="true"></div>

        <main class="relative z-10 mx-auto max-w-5xl space-y-6">
            <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-violet-300">Student workspace</p>
                    <h1 class="mt-2 text-3xl font-bold tracking-tight text-[#F8FAFC]">Practice your coding logic</h1>
                    <p class="mt-2 max-w-2xl text-sm leading-6 text-[#A59EB8]">Generate a topic quiz or create one from your own course PDF.</p>
                </div>
                <a href="{{ route('student.challenges.index') }}" class="app-secondary-button inline-flex min-h-11 items-center justify-center px-4 text-sm font-semibold">
                    Explore coding challenges
                </a>
            </header>

            <div class="grid gap-6 lg:grid-cols-2">
                <section class="app-glass-panel rounded-2xl border p-5 sm:p-7" aria-labelledby="topic-quiz-heading"
                    x-data="{
                        language: 'PHP',
                        topic: '',
                        isGenerating: false,
                        progress: 0,
                        statusMessage: '',
                        errorMessage: '',
                        _timer: null,
                        startProgress() {
                            this.progress = 3;
                            this.statusMessage = 'Preparing your practice quiz...';
                            this._timer = setInterval(() => {
                                if (this.progress < 98) {
                                    this.progress = Math.min(98, this.progress + Math.max(0.2, (98 - this.progress) * 0.05));
                                }
                                if (this.progress >= 75) this.statusMessage = 'Finalizing your questions...';
                                else if (this.progress >= 25) this.statusMessage = 'Building code-tracing challenges...';
                            }, 400);
                        },
                        stopProgress() {
                            if (this._timer) { clearInterval(this._timer); this._timer = null; }
                        },
                        async generateQuiz() {
                            if (!this.topic.trim() || this.isGenerating) return;
                            this.isGenerating = true;
                            this.errorMessage = '';
                            this.startProgress();
                            try {
                                const response = await fetch('{{ route('student.quiz.generate') }}', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'Accept': 'application/json',
                                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                    },
                                    body: JSON.stringify({ language: this.language, topic: this.topic.trim() }),
                                });
                                const data = await response.json();
                                if (!response.ok || !data.success) {
                                    const validationMessage = data.errors ? Object.values(data.errors).flat()[0] : null;
                                    throw new Error(validationMessage || data.message || `Request failed (${response.status})`);
                                }
                                const redirectUrl = new URL(data.redirect_url, window.location.origin);
                                if (redirectUrl.origin !== window.location.origin) throw new Error('Invalid quiz destination.');
                                this.stopProgress();
                                this.progress = 100;
                                this.statusMessage = 'Your quiz is ready. Loading...';
                                window.location.href = redirectUrl.pathname + redirectUrl.search + redirectUrl.hash;
                            } catch (error) {
                                this.stopProgress();
                                this.errorMessage = error.message || 'Quiz generation failed. Please try again.';
                                this.isGenerating = false;
                                this.progress = 0;
                            }
                        }
                    }">
                    <h2 id="topic-quiz-heading" class="text-xl font-bold text-[#F8FAFC]">Practice a topic</h2>
                    <p class="mt-1 text-sm leading-6 text-[#A59EB8]">Build a multiple-choice quiz from a programming concept.</p>

                    <form @submit.prevent="generateQuiz" class="mt-6 space-y-4" x-show="!isGenerating">
                        <div>
                            <label for="topic-language" class="auth-label">Programming language</label>
                            <select id="topic-language" x-model="language" class="auth-input mt-2">
                                <option value="PHP">PHP</option>
                                <option value="JavaScript">JavaScript</option>
                                <option value="Python">Python</option>
                                <option value="Java">Java</option>
                            </select>
                        </div>
                        <div>
                            <label for="topic" class="auth-label">Topic</label>
                            <input type="text" id="topic" x-model="topic" placeholder="e.g. Arrays, loops" required maxlength="255" class="auth-input mt-2">
                        </div>
                        <button type="submit" :disabled="!topic.trim() || isGenerating" class="auth-primary-button inline-flex min-h-12 w-full items-center justify-center px-5 text-sm font-semibold disabled:cursor-not-allowed disabled:opacity-60">
                            Generate topic quiz
                        </button>
                        <p x-show="errorMessage" x-text="errorMessage" x-cloak class="rounded-lg border border-rose-300/25 bg-rose-400/10 px-4 py-3 text-sm text-rose-100" role="alert"></p>
                    </form>

                    <div x-show="isGenerating" x-cloak class="mt-6 space-y-4" role="status" aria-live="polite">
                        <p class="font-semibold text-violet-100" x-text="statusMessage"></p>
                        <div class="h-3 overflow-hidden rounded-full bg-white/10" role="progressbar" aria-label="Quiz generation progress" aria-valuemin="0" aria-valuemax="100" :aria-valuenow="Math.round(progress)">
                            <div class="h-full rounded-full bg-gradient-to-r from-violet-600 to-violet-400 transition-all duration-700" :style="'width: ' + Math.round(progress) + '%'"></div>
                        </div>
                    </div>
                </section>

                <section class="app-glass-panel rounded-2xl border p-5 sm:p-7" aria-labelledby="document-quiz-heading"
                    x-data="{
                        title: '',
                        language: 'PHP',
                        count: '',
                        quizFormat: 'multiple_choice',
                        fileName: '',
                        isGenerating: false,
                        progress: 0,
                        errorMessage: '',
                        statusMessage: '',
                        _timer: null,
                        selectPdf(event) { this.fileName = event.target.files[0]?.name || ''; this.errorMessage = ''; },
                        startProgress() {
                            this.progress = 3;
                            this.statusMessage = 'Uploading PDF and extracting text...';
                            this._timer = setInterval(() => {
                                if (this.progress < 98) this.progress = Math.min(98, this.progress + Math.max(0.2, (98 - this.progress) * 0.05));
                                this.statusMessage = this.progress >= 65 ? 'Generating and checking questions...' : 'Analyzing your course material...';
                            }, 500);
                        },
                        stopProgress() { if (this._timer) { clearInterval(this._timer); this._timer = null; } },
                        async generateFromPdf() {
                            if (this.isGenerating) return;
                            const file = this.$refs.pdf.files[0];
                            if (!file) { this.errorMessage = 'Select a PDF file to continue.'; return; }
                            this.isGenerating = true;
                            this.errorMessage = '';
                            this.startProgress();
                            try {
                                const formData = new FormData();
                                formData.append('pdf', file);
                                formData.append('title', this.title.trim());
                                formData.append('language', this.language);
                                formData.append('count', this.count || 0);
                                formData.append('quiz_format', this.quizFormat);
                                formData.append('_token', '{{ csrf_token() }}');
                                const response = await fetch('{{ route('student.quiz.generate.document') }}', {
                                    method: 'POST',
                                    headers: { 'Accept': 'application/json' },
                                    body: formData,
                                });
                                const data = await response.json();
                                if (!response.ok || !data.success) throw new Error(data.message || `Request failed (${response.status})`);
                                const redirectUrl = new URL(data.redirect_url, window.location.origin);
                                if (redirectUrl.origin !== window.location.origin) throw new Error('Invalid quiz destination.');
                                this.stopProgress();
                                this.progress = 100;
                                this.statusMessage = `${data.question_count} questions generated. Loading your quiz...`;
                                window.location.href = redirectUrl.pathname + redirectUrl.search + redirectUrl.hash;
                            } catch (error) {
                                this.stopProgress();
                                this.errorMessage = error.message || 'Quiz generation failed. Please try again later.';
                                this.isGenerating = false;
                                this.progress = 0;
                            }
                        }
                    }">
                    <h2 id="document-quiz-heading" class="text-xl font-bold text-[#F8FAFC]">Generate from a PDF</h2>
                    <p class="mt-1 text-sm leading-6 text-[#A59EB8]">Use your course material to create a source-grounded quiz.</p>

                    <form @submit.prevent="generateFromPdf" class="mt-6 space-y-4" x-show="!isGenerating">
                        <div>
                            <label for="document-title" class="auth-label">Quiz title</label>
                            <input id="document-title" type="text" x-model="title" required maxlength="255" class="auth-input mt-2" placeholder="e.g. Data structures review">
                        </div>
                        <div>
                            <label for="document-language" class="auth-label">Programming language</label>
                            <select id="document-language" x-model="language" class="auth-input mt-2">
                                <option value="PHP">PHP</option>
                                <option value="JavaScript">JavaScript</option>
                                <option value="Python">Python</option>
                                <option value="Java">Java</option>
                            </select>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label for="document-count" class="auth-label">Question count <span class="font-normal text-[#A59EB8]">(optional)</span></label>
                                <input id="document-count" type="number" x-model="count" min="0" max="50" class="auth-input mt-2" placeholder="Automatic">
                                <p class="mt-1 text-xs text-[#A59EB8]">Blank or 0: generate up to 50.</p>
                            </div>
                            <div>
                                <label for="document-format" class="auth-label">Question format</label>
                                <select id="document-format" x-model="quizFormat" class="auth-input mt-2">
                                    <option value="multiple_choice">Multiple choice</option>
                                    <option value="fill_in_the_blank">Fill in the blank</option>
                                </select>
                                <p class="mt-1 text-xs text-[#A59EB8]" x-text="quizFormat === 'multiple_choice' ? 'Four answer choices.' : 'Type a short answer.'"></p>
                            </div>
                        </div>
                        <div>
                            <label for="document-pdf" class="auth-label">PDF file</label>
                            <input id="document-pdf" type="file" x-ref="pdf" accept="application/pdf" required @change="selectPdf($event)" class="professor-file-input mt-2 block w-full" aria-describedby="student-pdf-guidance">
                            <p id="student-pdf-guidance" class="mt-1 text-xs text-[#A59EB8]">PDF only · maximum 20 MB. Text is extracted locally when possible; if extraction requires a provider, Gemini may receive the PDF. Extracted text is sent to Groq for quiz generation. Review provider data-handling terms before uploading sensitive material.</p>
                            <p x-show="fileName" x-cloak class="mt-2 break-all text-xs text-violet-100" aria-live="polite"><span class="font-semibold">Selected:</span> <span x-text="fileName"></span></p>
                        </div>
                        <button type="submit" :disabled="isGenerating" class="auth-primary-button inline-flex min-h-12 w-full items-center justify-center px-5 text-sm font-semibold disabled:cursor-not-allowed disabled:opacity-60">
                            Generate quiz from PDF
                        </button>
                        <p x-show="errorMessage" x-text="errorMessage" x-cloak class="rounded-lg border border-rose-300/25 bg-rose-400/10 px-4 py-3 text-sm text-rose-100" role="alert"></p>
                    </form>

                    <div x-show="isGenerating" x-cloak class="mt-6 space-y-4" role="status" aria-live="polite">
                        <p class="font-semibold text-violet-100" x-text="statusMessage"></p>
                        <div class="h-3 overflow-hidden rounded-full bg-white/10" role="progressbar" aria-label="Document quiz generation progress" aria-valuemin="0" aria-valuemax="100" :aria-valuenow="Math.round(progress)">
                            <div class="h-full rounded-full bg-gradient-to-r from-violet-600 to-violet-400 transition-all duration-700" :style="'width: ' + Math.round(progress) + '%'"></div>
                        </div>
                        <p class="text-xs text-[#A59EB8]">Keep this page open while the document is processed.</p>
                    </div>
                </section>
            </div>
        </main>
    </div>
</x-app-layout>
