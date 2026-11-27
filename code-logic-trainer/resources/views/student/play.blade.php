<x-app-layout>
    <div class="auth-shell relative isolate min-h-[calc(100vh-4rem)] overflow-hidden px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
        <div class="ambient-orb ambient-orb--violet" aria-hidden="true"></div>
        <div class="ambient-orb ambient-orb--indigo" aria-hidden="true"></div>
        <div class="ambient-orb ambient-orb--plum" aria-hidden="true"></div>

        <main class="relative z-10 mx-auto max-w-3xl space-y-6">
            <section class="app-glass-panel p-4" aria-label="Quiz progress">
                <div class="mb-2 flex items-center justify-between">
                    <span class="text-sm font-bold text-accent-200">Quiz progress</span>
                    <span id="progress-text" class="text-sm font-bold tabular-nums text-ink-text">0%</span>
                </div>
                <div class="h-3 w-full overflow-hidden rounded-full bg-white/10">
                    <div id="progress-bar" class="h-full w-0 rounded-full bg-accent-500 transition-[width] duration-500"></div>
                </div>
            </section>

            <section class="app-glass-panel p-5 sm:p-7" id="quiz-box">
                <div class="mb-5 flex flex-col gap-3 border-b border-white/10 pb-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-xs font-semibold tracking-wide text-accent-300">{{ $session->language }} practice</p>
                        <h1 class="mt-1 text-xl font-bold tracking-tight text-ink-text">{{ $session->topic }}</h1>
                    </div>
                    <div class="flex flex-col items-end gap-2">
                        <span class="text-sm font-semibold tabular-nums text-ink-muted" id="question-counter">Question 1 of {{ count($session->questions) }}</span>
                        <span id="question-timer" role="timer" aria-live="off" class="rounded-full border border-accent-300/20 bg-accent-300/10 px-4 py-2 text-base font-bold tabular-nums text-accent-100">{{ sprintf('%02d:%02d', intdiv($secondsPerQuestion, 60), $secondsPerQuestion % 60) }}</span>
                    </div>
                </div>

                @foreach ($session->questions as $index => $question)
                    <div class="question-slide {{ $index > 0 ? 'hidden' : '' }}" data-index="{{ $index }}"
                        data-question-id="{{ $question->id }}" data-question-type="{{ $question->question_type ?? 'multiple_choice' }}">
                        <pre class="mb-6 overflow-x-auto whitespace-pre-wrap rounded-[var(--radius-control)] border border-white/10 bg-ink-950/70 p-4 font-mono text-sm leading-6 text-ink-soft">{{ $question->question_text }}</pre>

                        @if (($question->question_type ?? 'multiple_choice') === 'multiple_choice')
                            <div class="mb-4 grid grid-cols-1 gap-3">
                                @foreach ($question->options ?? [] as $option)
                                    <button type="button" class="option-btn app-secondary-button min-h-12 w-full px-4 py-3 text-left text-sm font-medium text-ink-soft hover:border-violet-300/40 hover:bg-violet-300/[0.08]" data-option="{{ $option }}">
                                        {{ $option }}
                                    </button>
                                @endforeach
                            </div>
                        @else
                            <div class="mb-4 space-y-3">
                                <label for="answer-{{ $question->id }}" class="auth-label">Your answer</label>
                                <input id="answer-{{ $question->id }}" type="text" maxlength="1000" class="auth-input fill-answer" placeholder="Type your answer" autocomplete="off">
                                <button type="button" class="auth-primary-button submit-fill-answer inline-flex min-h-11 items-center justify-center px-5 text-sm font-semibold">Check answer</button>
                            </div>
                        @endif

                        <div class="answer-feedback hidden rounded-xl border border-white/10 p-4" role="status" aria-live="polite">
                            <p class="feedback-status font-bold text-ink-text"></p>
                            <p class="feedback-correct-answer mt-2 hidden text-emerald-100">
                                <span class="font-semibold">Correct answer:</span>
                                <span class="feedback-correct-answer-text"></span>
                            </p>
                            <p class="feedback-explanation mt-2 hidden leading-6 text-ink-soft">
                                <span class="font-semibold">Explanation:</span>
                                <span class="feedback-explanation-text"></span>
                            </p>
                            <button type="button" class="continue-question auth-primary-button mt-4 hidden min-h-10 px-4 py-2 text-sm font-semibold">Continue</button>
                        </div>
                        <div class="answer-feedback-error mt-3 hidden rounded-[var(--radius-control)] border border-rose-300/25 bg-rose-400/10 p-3 text-sm leading-5 text-rose-100" role="alert">
                            <p>We couldn’t check your answer. Please try again.</p>
                            <button type="button" class="retry-answer mt-2 min-h-11 font-semibold underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-violet-300">Retry</button>
                        </div>
                    </div>
                @endforeach
            </section>

            <div id="submission-error" class="app-glass-panel hidden border-rose-300/25 p-6 text-center text-rose-100" role="alert">
                <h2 class="text-lg font-bold tracking-tight text-ink-text">We couldn’t submit your quiz</h2>
                <p id="submission-error-message" class="mt-2"></p>
                <a href="{{ route('student.dashboard') }}" class="auth-primary-button mt-4 inline-flex min-h-11 items-center px-6 py-3 font-semibold">Return to Dashboard</a>
            </div>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const slides = Array.from(document.querySelectorAll('.question-slide'));
            const totalQuestions = slides.length;
            let currentIndex = 0;
            const answers = {};
            const responseTimes = {};
            const progressBar = document.getElementById('progress-bar');
            const progressText = document.getElementById('progress-text');
            const counter = document.getElementById('question-counter');
            const timer = document.getElementById('question-timer');
            const questionTimeLimit = @json($secondsPerQuestion);
            const timeoutAnswer = @json(\App\Http\Controllers\StudentQuizController::TIMEOUT_ANSWER);
            let timerInterval = null;
            let remainingSeconds = questionTimeLimit;
            let questionStartedAt = Date.now();

            function updateProgress() {
                const percentage = Math.round((currentIndex / totalQuestions) * 100);
                progressBar.style.width = percentage + '%';
                progressText.textContent = percentage + '%';
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
                timerInterval = setInterval(() => {
                    remainingSeconds--;
                    renderTimer();
                    if (remainingSeconds <= 0) {
                        stopTimer();
                        const slide = slides[currentIndex];
                        answers[slide.dataset.questionId] = timeoutAnswer;
                        responseTimes[slide.dataset.questionId] = questionTimeLimit * 1000;
                        checkAnswer(slide, null, true);
                    }
                }, 1000);
            }

            function showSubmissionError(message) {
                document.getElementById('submission-error-message').textContent = message;
                document.getElementById('submission-error').classList.remove('hidden');
            }

            function submitQuiz() {
                updateProgress();
                document.getElementById('quiz-box').classList.add('hidden');
                fetch("{{ route('student.quiz.submit', $session->id) }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ answers, response_times: responseTimes })
                }).then(async response => {
                    const data = await response.json();
                    if (!response.ok || !data.success) throw new Error(data.message || 'Could not submit quiz answers.');
                    window.location.href = data.redirect_url;
                }).catch(error => showSubmissionError(error.message));
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

            function checkAnswer(slide, selectedAnswer, timedOut) {
                const buttons = Array.from(slide.querySelectorAll('.option-btn'));
                const fillInput = slide.querySelector('.fill-answer');
                const fillSubmit = slide.querySelector('.submit-fill-answer');
                const feedback = slide.querySelector('.answer-feedback');
                const feedbackError = slide.querySelector('.answer-feedback-error');

                buttons.forEach(button => button.disabled = true);
                if (fillInput) fillInput.disabled = true;
                if (fillSubmit) fillSubmit.disabled = true;
                responseTimes[slide.dataset.questionId] = Math.max(0, Date.now() - questionStartedAt);
                feedbackError.classList.add('hidden');

                fetch("{{ route('student.quiz.answer', $session->id) }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        question_id: slide.dataset.questionId,
                        answer: selectedAnswer,
                        timed_out: timedOut
                    })
                }).then(async response => {
                    const data = await response.json();
                    if (!response.ok || !data.success) throw new Error(data.message || 'Could not check this answer.');

                    feedback.classList.remove('hidden', 'border-emerald-300/25', 'bg-emerald-300/[0.05]', 'border-rose-300/25', 'bg-rose-300/[0.05]');
                    const feedbackStatus = feedback.querySelector('.feedback-status');
                    const feedbackCorrectAnswer = feedback.querySelector('.feedback-correct-answer');
                    const feedbackExplanation = feedback.querySelector('.feedback-explanation');

                    if (data.is_correct) {
                        feedback.classList.add('border-emerald-300/25', 'bg-emerald-300/[0.05]');
                        feedbackStatus.className = 'feedback-status font-bold text-emerald-100';
                        feedbackStatus.textContent = 'Correct!';
                        if (selectedAnswer !== null && buttons.length) {
                            buttons.find(button => button.dataset.option === selectedAnswer)?.classList.add('border-emerald-300/40', 'bg-emerald-300/10');
                        }
                    } else {
                        feedback.classList.add('border-rose-300/25', 'bg-rose-300/[0.05]');
                        feedbackStatus.className = 'feedback-status font-bold text-rose-100';
                        feedbackStatus.textContent = timedOut ? 'Time expired. Your answer is incorrect.' : 'Incorrect.';
                        feedbackCorrectAnswer.querySelector('.feedback-correct-answer-text').textContent = data.correct_answer;
                        feedbackCorrectAnswer.classList.remove('hidden');
                        feedbackExplanation.querySelector('.feedback-explanation-text').textContent = data.explanation || 'No explanation was provided.';
                        feedbackExplanation.classList.remove('hidden');
                        if (selectedAnswer !== null && buttons.length) {
                            buttons.find(button => button.dataset.option === selectedAnswer)?.classList.add('border-rose-300/40', 'bg-rose-300/10');
                            buttons.find(button => button.dataset.option.trim() === String(data.correct_answer).trim())?.classList.add('border-emerald-300/40', 'bg-emerald-300/10');
                        }
                    }

                    feedback.querySelector('.continue-question').classList.remove('hidden');
                }).catch(() => {
                    feedbackError.classList.remove('hidden');
                    buttons.forEach(button => button.disabled = false);
                    if (fillInput) fillInput.disabled = false;
                    if (fillSubmit) fillSubmit.disabled = false;
                });
            }

            slides.forEach(slide => {
                const buttons = slide.querySelectorAll('.option-btn');
                const fillInput = slide.querySelector('.fill-answer');
                const fillSubmit = slide.querySelector('.submit-fill-answer');
                const retryButton = slide.querySelector('.retry-answer');
                const continueButton = slide.querySelector('.continue-question');

                buttons.forEach(button => button.addEventListener('click', function() {
                    const answer = this.getAttribute('data-option');
                    answers[slide.dataset.questionId] = answer;
                    stopTimer();
                    checkAnswer(slide, answer, false);
                }));

                fillSubmit?.addEventListener('click', () => {
                    if (!fillInput) return;
                    answers[slide.dataset.questionId] = fillInput.value;
                    stopTimer();
                    checkAnswer(slide, fillInput.value, false);
                });

                retryButton.addEventListener('click', () => {
                    const answer = answers[slide.dataset.questionId];
                    if (fillInput && answer !== timeoutAnswer) fillInput.value = answer || '';
                    checkAnswer(slide, answer === timeoutAnswer ? null : answer, answer === timeoutAnswer);
                });

                continueButton.addEventListener('click', () => {
                    continueButton.disabled = true;
                    advanceQuestion(slide);
                });
            });

            updateProgress();
            startTimer();
        });
    </script>
</x-app-layout>
