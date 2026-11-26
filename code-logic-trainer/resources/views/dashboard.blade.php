<x-app-layout>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <style>
        [x-cloak] {
            display: none !important;
        }
    </style>

    <div class="mx-auto max-w-3xl rounded-[var(--radius-panel)] border border-white/10 bg-ink-800/80 px-5 py-7 shadow-[0_20px_48px_-32px_rgba(0,0,0,0.8)] sm:px-8 sm:py-9">
        @if (isset($session) && $session)
            <h2 class="mb-4 text-xl font-bold tracking-tight text-accent-200">
                Quiz: {{ $session->language }} - {{ $session->topic }}
            </h2>

            <div class="mb-6">
                <a href="{{ route('student.play', $session->id) }}"
                    class="app-secondary-button inline-flex min-h-11 items-center justify-center px-4 py-2 text-sm font-semibold">
                    Play Latest Quiz
                </a>
            </div>

            <div id="quiz-container">
                @foreach ($session->questions as $index => $question)
                    <div class="question-card mb-6 rounded-[var(--radius-control)] border border-white/10 bg-ink-750/70 p-4 sm:p-5 {{ $index > 0 ? 'hidden' : '' }}"
                        data-index="{{ $index }}">
                        <p class="mb-3 font-semibold text-ink-soft">Q{{ $index + 1 }}:</p>

                        <pre class="mb-4 whitespace-pre-wrap rounded-[var(--radius-field)] border border-white/10 bg-ink-950/80 p-3 font-mono text-sm leading-6 text-accent-200">{{ $question->question_text }}</pre>

                        <div class="space-y-2">
                            @foreach ($question->options as $option)
                                <label class="block rounded-[var(--radius-field)] border border-white/10 bg-ink-800/80 p-3 text-sm leading-6 text-ink-muted cursor-default">
                                    <input type="radio" disabled class="mr-2"> {{ $option }}
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="py-5 text-center sm:py-8" x-data="{
                language: 'PHP',
                topic: '',
                isGenerating: false,
                progress: 0,
                statusMessage: '',
                errorMessage: '',
                _timer: null,

                startProgress() {
                    this.progress = 3;
                    this.statusMessage = 'Connecting to Gemini AI...';
                    this._timer = setInterval(() => {
                        if (this.progress < 98) {
                            const remaining = 98 - this.progress;
                            this.progress = Math.min(98, this.progress + Math.max(0.2, remaining * 0.05));
                        }
                        if (this.progress >= 80) {
                            this.statusMessage = 'Finalizing quiz questions and code snippets...';
                        } else if (this.progress >= 50) {
                            this.statusMessage = 'Synthesizing custom code-tracing challenges...';
                        } else if (this.progress >= 20) {
                            this.statusMessage = 'Analyzing programming patterns & syntax rules...';
                        }
                    }, 400);
                },

                stopProgress() {
                    if (this._timer) {
                        clearInterval(this._timer);
                        this._timer = null;
                    }
                },

                async generateQuiz() {
                    if (!this.topic.trim() || this.isGenerating) return;

                    this.isGenerating = true;
                    this.errorMessage = '';
                    this.progress = 0;
                    this.startProgress();

                    try {
                        const response = await fetch('{{ route('student.quiz.generate') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            },
                            body: JSON.stringify({
                                language: this.language,
                                topic: this.topic.trim(),
                            }),
                        });

                        const data = await response.json().catch(() => ({}));

                        console.log('Response from server:', data);

                        if (!response.ok || !data.success) {
                            throw new Error(data.message || `Request failed (${response.status})`);
                        }

                        this.stopProgress();
                        this.progress = 100;
                        this.statusMessage = 'Summit path ready! Loading your quiz...';

                        console.log('Redirecting to:', data.redirect_url);

                        if (data.redirect_url) {
                            // Let the completed progress state remain visible briefly.
                            setTimeout(() => {
                                window.location.assign(data.redirect_url);
                            }, 600);
                        } else {
                            throw new Error('Server did not return a redirect URL.');
                        }

                    } catch (err) {
                        console.error('Fetch error:', err);
                        this.stopProgress();
                        this.errorMessage = err.message || 'Something went wrong. Please try again.';
                        this.isGenerating = false;
                        this.progress = 0;
                    }
                }
            }">

                <h2 class="mb-2 text-2xl font-bold tracking-tight text-ink-text sm:text-[1.75rem]">Welcome to your Code Logic Trainer!</h2>
                <p class="mb-6 text-sm leading-6 text-ink-subtle sm:text-base">Choose your parameters and watch our AI build your mountain path!</p>

                <form @submit.prevent="generateQuiz" class="space-y-4 max-w-md mx-auto text-left"
                    x-show="!isGenerating">
                    <div>
                        <label for="language" class="block text-sm font-medium text-ink-soft">Programming Language</label>
                        <select id="language" name="language" x-model="language"
                            class="app-action-select mt-2 block w-full">
                            <option value="PHP">PHP</option>
                            <option value="JavaScript">JavaScript</option>
                            <option value="Python">Python</option>
                            <option value="Java">Java</option>
                        </select>
                    </div>

                    <div>
                        <label for="topic" class="block text-sm font-medium text-ink-soft">Topic</label>
                        <input type="text" id="topic" name="topic" x-model="topic"
                            placeholder="e.g. Arrays, Loops, Object-Oriented Programming" required
                            class="app-action-select mt-2 block w-full">
                    </div>

                    <button type="submit" :disabled="!topic.trim() || isGenerating"
                        class="auth-primary-button min-h-11 w-full justify-center px-4 py-2 text-sm font-semibold disabled:cursor-not-allowed disabled:opacity-50">
                        Generate AI Quiz
                    </button>

                    <p x-show="errorMessage" x-text="errorMessage" class="mt-2 text-sm text-rose-300" x-cloak></p>
                </form>

                <div x-show="isGenerating" x-cloak class="space-y-6 py-6 max-w-md mx-auto text-center">
                    <div class="space-y-2">
                        <p class="text-lg font-semibold text-accent-200" x-text="statusMessage"></p>
                        <p class="text-sm leading-6 text-ink-subtle">Please stay on this page while Gemini constructs your code
                            trail...</p>
                    </div>

                    <div class="h-2.5 w-full overflow-hidden rounded-full bg-white/10">
                        <div class="h-full rounded-full bg-accent-500 transition-[width] duration-700 ease-out"
                            :style="'width: ' + Math.round(progress) + '%'"></div>
                    </div>

                    <p class="font-mono text-xs tabular-nums text-ink-subtle" x-text="Math.round(progress) + '% completed'"></p>
                </div>
            </div>
        @endif
    </div>
</x-app-layout>
