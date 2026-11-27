<x-app-layout>
    <div class="auth-shell relative isolate min-h-[calc(100vh-4rem)] overflow-hidden px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
        <div class="ambient-orb ambient-orb--violet" aria-hidden="true"></div>
        <div class="ambient-orb ambient-orb--indigo" aria-hidden="true"></div>
        <div class="ambient-orb ambient-orb--plum" aria-hidden="true"></div>

        <main class="relative z-10 mx-auto max-w-3xl space-y-6">
            <div id="tab-switch-warning" class="hidden rounded-[var(--radius-control)] border border-amber-300/25 bg-amber-400/10 px-4 py-3 text-sm leading-6 text-amber-100 shadow-sm" role="status" aria-live="polite">
                <span id="tab-switch-warning-message"></span>
            </div>

            <section class="app-glass-panel p-4" aria-label="Live quiz progress">
                <div class="mb-2 flex items-center justify-between gap-3">
                    <span class="truncate text-sm font-bold text-accent-200">{{ $session->quiz->title }}</span>
                    <span id="progress-text" class="text-sm font-bold tabular-nums text-ink-text">0%</span>
                </div>
                <div class="h-3 w-full overflow-hidden rounded-full bg-white/10">
                    <div id="progress-bar" class="h-full w-0 rounded-full bg-accent-500 transition-[width] duration-500"></div>
                </div>
            </section>
            <p id="progress-sync-warning" class="hidden rounded-[var(--radius-control)] border border-amber-300/25 bg-amber-400/10 px-4 py-3 text-sm leading-6 text-amber-100"
                role="status" aria-live="polite"></p>

            <section class="app-glass-panel p-5 sm:p-7" id="quiz-box">
                <div class="mb-5 flex flex-col gap-3 border-b border-white/10 pb-4 sm:flex-row sm:items-center sm:justify-between">
                    <h1 class="text-xl font-bold tracking-tight text-ink-text">Live quiz</h1>
                    <div class="flex flex-wrap items-center gap-3">
                        <span class="text-sm font-semibold tabular-nums text-ink-muted" id="question-counter">Question 1 of {{ count($session->quiz->questions) }}</span>
                        <span id="question-timer" role="timer" aria-live="off" class="rounded-full border border-accent-300/20 bg-accent-300/10 px-3 py-1.5 text-sm font-bold tabular-nums text-accent-100">{{ sprintf('%02d:%02d', intdiv($secondsPerQuestion, 60), $secondsPerQuestion % 60) }}</span>
                    </div>
                </div>

                @foreach ($session->quiz->questions as $index => $question)
                    <div class="question-slide {{ $index > 0 ? 'hidden' : '' }}" data-index="{{ $index }}"
                        data-question-id="{{ $question->id }}" data-question-type="{{ $question->question_type ?? 'multiple_choice' }}">
                        <pre class="mb-6 overflow-x-auto whitespace-pre-wrap rounded-[var(--radius-control)] border border-white/10 bg-ink-950/70 p-4 font-mono text-sm leading-6 text-ink-soft">{{ $question->question_text }}</pre>

                        @if (($question->question_type ?? 'multiple_choice') === 'multiple_choice')
                            <div class="grid grid-cols-1 gap-3">
                                @foreach ($question->options ?? [] as $option)
                                    <button type="button" class="option-btn app-secondary-button min-h-12 w-full px-4 py-3 text-left text-sm font-medium text-ink-soft hover:border-accent-300/40 hover:bg-accent-300/[0.08]" data-option="{{ $option }}">
                                        {{ $option }}
                                    </button>
                                @endforeach
                            </div>
                        @else
                            <div class="space-y-3">
                                <label for="answer-{{ $question->id }}" class="auth-label">Your answer <span class="font-normal text-ink-subtle">(optional)</span></label>
                                <input id="answer-{{ $question->id }}" type="text" maxlength="1000" class="auth-input fill-answer" placeholder="Type a short answer" autocomplete="off">
                                <button type="button" class="auth-primary-button submit-fill-answer inline-flex min-h-11 items-center justify-center px-5 text-sm font-semibold">Submit answer</button>
                            </div>
                        @endif
                    </div>
                @endforeach
            </section>

            <div id="submission-error" class="app-glass-panel hidden border-rose-300/25 p-6 text-center text-rose-100" role="alert">
                <h2 class="text-lg font-bold tracking-tight text-ink-text">We couldn’t submit your quiz</h2>
                <p id="submission-error-message" class="mt-2"></p>
                <a href="{{ route('student.live.lobby', $session->id) }}" class="auth-primary-button mt-4 inline-flex min-h-11 items-center px-6 py-3 font-semibold">Return to Quiz Lobby</a>
            </div>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const slides = Array.from(document.querySelectorAll('.question-slide'));
            const totalQuestions = slides.length;
            let currentIndex = 0;
            const answers = {};
            const responseTimes = {};
            const counter = document.getElementById('question-counter');
            const progressBar = document.getElementById('progress-bar');
            const progressText = document.getElementById('progress-text');
            const timer = document.getElementById('question-timer');
            const questionTimeLimit = @json($secondsPerQuestion);
            let timerInterval = null;
            let remainingSeconds = questionTimeLimit;
            let questionStartedAt = Date.now();
            const tabSwitchWarning = document.getElementById('tab-switch-warning');
            const tabSwitchWarningMessage = document.getElementById('tab-switch-warning-message');
            const progressSyncWarning = document.getElementById('progress-sync-warning');
            const heartbeatUrl = @json(route('student.live.heartbeat', $session->id));
            const pendingProgressSaves = new Set();
            let examActive = @json($examActive);
            let heartbeatInterval = null;
            let lastTabSwitchSentAt = 0;
            let tabSwitchWarningTimeout = null;

            async function sendHeartbeat() {
                if (!examActive) return;

                try {
                    const response = await fetch(heartbeatUrl, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        }
                    });
                    const data = await response.json();
                    if (!response.ok || !data.alive) {
                        throw new Error(data.message || 'The live session heartbeat could not be recorded.');
                    }

                    progressSyncWarning.classList.add('hidden');
                } catch (error) {
                    console.error('Could not update live quiz activity:', error);
                    if (examActive) {
                        progressSyncWarning.textContent = 'Your connection to the live session is interrupted. Check your connection before continuing.';
                        progressSyncWarning.classList.remove('hidden');
                    }
                }
            }

            function renderTimer() {
                const minutes = Math.floor(remainingSeconds / 60);
                const seconds = remainingSeconds % 60;
                timer.textContent = `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
                timer.classList.toggle('border-rose-300/30', remainingSeconds <= 3);
                timer.classList.toggle('text-rose-100', remainingSeconds <= 3);
                timer.classList.toggle('border-accent-300/20', remainingSeconds > 3);
                timer.classList.toggle('text-accent-100', remainingSeconds > 3);
            }

            function stopTimer() {
                if (timerInterval !== null) {
                    clearInterval(timerInterval);
                    timerInterval = null;
                }
            }

            function startTimer() {
                remainingSeconds = questionTimeLimit;
                questionStartedAt = Date.now();
                renderTimer();
                timerInterval = window.setInterval(() => {
                    remainingSeconds--;
                    renderTimer();
                    if (remainingSeconds <= 0) {
                        stopTimer();
                        answerQuestion(slides[currentIndex], '', null);
                    }
                }, 1000);
            }

            function showTabSwitchWarning(message) {
                tabSwitchWarningMessage.textContent = message;
                tabSwitchWarning.classList.remove('hidden');
                window.clearTimeout(tabSwitchWarningTimeout);
                tabSwitchWarningTimeout = window.setTimeout(() => {
                    tabSwitchWarning.classList.add('hidden');
                }, 8000);
            }

            function flagTabSwitch() {
                if (!examActive) return;

                const now = Date.now();
                if (now - lastTabSwitchSentAt < 1500) return;
                lastTabSwitchSentAt = now;

                fetch("{{ route('student.live.flag-tab-switch', $session->id) }}", {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    keepalive: true
                }).then(async response => {
                    const data = await response.json();
                    if (!response.ok || !data.flagged) {
                        throw new Error(data.message || 'The tab-switch alert could not be recorded.');
                    }

                    showTabSwitchWarning(`Tab switch recorded and reported to your professor (${data.tab_switch_count} total).`);
                }).catch(error => {
                    console.error('Could not record live quiz tab-switch flag:', error);
                    showTabSwitchWarning('Your tab switch could not be recorded. Return to the quiz and notify your professor.');
                });
            }

            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'hidden') flagTabSwitch();
            });
            window.addEventListener('blur', flagTabSwitch);

            function updateProgress() {
                const percentage = Math.round((currentIndex / totalQuestions) * 100);
                progressBar.style.width = percentage + '%';
                progressText.textContent = percentage + '%';
            }

            async function saveProgress(questionId, answer) {
                try {
                    const response = await fetch("{{ route('student.live.progress', $session->id) }}", {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({ question_id: questionId, answer, response_time_ms: responseTimes[questionId] ?? null })
                    });
                    const data = await response.json();
                    if (!response.ok || !data.saved) throw new Error(data.message || 'Could not sync quiz progress.');

                    progressSyncWarning.classList.add('hidden');
                } catch (error) {
                    console.error('Could not sync live quiz progress:', error);
                    if (examActive) {
                        progressSyncWarning.textContent = 'Progress could not sync just now. Your final submission will still include your answers.';
                        progressSyncWarning.classList.remove('hidden');
                    }
                }
            }

            function queueProgressSave(questionId, answer) {
                const pending = saveProgress(questionId, answer).finally(() => pendingProgressSaves.delete(pending));
                pendingProgressSaves.add(pending);
            }

            async function submitQuiz() {
                stopTimer();
                examActive = false;
                if (heartbeatInterval !== null) {
                    window.clearInterval(heartbeatInterval);
                    heartbeatInterval = null;
                }
                updateProgress();
                document.getElementById('quiz-box').classList.add('hidden');
                await Promise.race([
                    Promise.all([...pendingProgressSaves]),
                    new Promise(resolve => window.setTimeout(resolve, 1500))
                ]);
                fetch("{{ route('student.live.submit', $session->id) }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ answers })
                }).then(async response => {
                    const data = await response.json();
                    if (!response.ok || !data.success) throw new Error(data.message || 'Could not submit quiz answers.');
                    window.location.href = data.redirect_url;
                }).catch(error => {
                    document.getElementById('submission-error-message').textContent = error.message;
                    document.getElementById('submission-error').classList.remove('hidden');
                });
            }

            function advanceQuestion(slide) {
                slide.classList.add('hidden');
                currentIndex++;
                if (currentIndex < totalQuestions) {
                    slides[currentIndex].classList.remove('hidden');
                    counter.textContent = `Question ${currentIndex + 1} of ${totalQuestions}`;
                    updateProgress();
                    startTimer();
                } else {
                    submitQuiz();
                }
            }

            function answerQuestion(slide, answer, button) {
                stopTimer();
                const controls = slide.querySelectorAll('.option-btn, .fill-answer, .submit-fill-answer');
                controls.forEach(control => control.disabled = true);
                answers[slide.dataset.questionId] = answer;
                responseTimes[slide.dataset.questionId] = Math.max(0, Date.now() - questionStartedAt);
                queueProgressSave(slide.dataset.questionId, answer);
                button?.classList.add('border-violet-300/40', 'bg-violet-300/10');
                window.setTimeout(() => advanceQuestion(slide), 3000);
            }

            slides.forEach(slide => {
                const buttons = slide.querySelectorAll('.option-btn');
                const fillInput = slide.querySelector('.fill-answer');
                const fillSubmit = slide.querySelector('.submit-fill-answer');

                buttons.forEach(button => button.addEventListener('click', () => {
                    answerQuestion(slide, button.getAttribute('data-option'), button);
                }));

                fillSubmit?.addEventListener('click', () => {
                    answerQuestion(slide, fillInput?.value ?? '', fillSubmit);
                });
            });

            updateProgress();
            startTimer();
            if (examActive) {
                sendHeartbeat();
                heartbeatInterval = window.setInterval(sendHeartbeat, 15000);
            }
        });
    </script>
</x-app-layout>
