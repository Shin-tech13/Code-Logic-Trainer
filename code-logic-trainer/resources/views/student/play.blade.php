<x-app-layout>
    <div class="max-w-3xl mx-auto py-10 px-6">

        <!-- Gamified Mountain Climbing Progress Header -->
        <div class="bg-white shadow rounded-lg p-4 mb-6">
            <div class="flex justify-between items-center mb-2">
                <span class="text-sm font-bold text-indigo-600">Mountain Summit Progress</span>
                <span id="progress-text" class="text-sm font-bold text-gray-700">0%</span>
            </div>
            <!-- Mountain Track -->
            <div class="relative w-full bg-gray-200 h-6 rounded-full overflow-hidden shadow-inner">
                <div id="progress-bar"
                    class="bg-gradient-to-r from-indigo-500 to-green-500 h-full w-0 transition-all duration-500"></div>
                <!-- Progress marker moving across -->
                <div id="climber-icon"
                    class="absolute top-1/2 left-0 h-3 w-3 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-indigo-600 bg-white transition-all duration-500">
                </div>
            </div>
        </div>

        <!-- Quiz Box -->
        <div class="bg-white shadow rounded-lg p-6" id="quiz-box">
            <div class="flex flex-col gap-3 mb-4 border-b pb-3 sm:flex-row sm:items-center sm:justify-between">
                <h2 class="text-lg font-bold text-gray-800">{{ $session->language }} : {{ $session->topic }}</h2>
                <div class="flex flex-col items-end gap-2">
                    <span class="text-sm text-gray-500 font-semibold" id="question-counter">Question 1 of
                        {{ count($session->questions) }}</span>
                    <span id="question-timer" role="timer" aria-live="off"
                        class="rounded-full bg-indigo-100 px-4 py-2 text-base font-bold tabular-nums text-indigo-800">
                        00:10
                    </span>
                </div>
            </div>

            @foreach ($session->questions as $index => $question)
                <div class="question-slide {{ $index > 0 ? 'hidden' : '' }}" data-index="{{ $index }}"
                    data-question-id="{{ $question->id }}">
                    <!-- Code Snippet / Question Text -->
                    <pre
                        class="bg-gray-900 text-green-400 p-4 rounded-lg mb-6 text-sm font-mono whitespace-pre-wrap overflow-x-auto shadow-inner">{{ $question->question_text }}</pre>

                    <!-- Options Grid -->
                    <div class="grid grid-cols-1 gap-3 mb-4">
                        @foreach ($question->options as $option)
                            <button type="button"
                                class="option-btn text-left p-4 border-2 border-gray-200 rounded-lg bg-white hover:bg-indigo-50 hover:border-indigo-300 font-medium text-gray-700 transition"
                                data-option="{{ $option }}">
                                {{ $option }}
                            </button>
                        @endforeach
                    </div>

                    <div class="answer-feedback hidden rounded-lg border p-4" role="status" aria-live="polite">
                        <p class="feedback-status font-bold"></p>
                        <p class="feedback-correct-answer mt-2 hidden text-green-800">
                            <span class="font-semibold">Correct answer:</span>
                            <span class="feedback-correct-answer-text"></span>
                        </p>
                        <p class="feedback-explanation mt-2 hidden text-gray-700">
                            <span class="font-semibold">Explanation:</span>
                            <span class="feedback-explanation-text"></span>
                        </p>
                        <button type="button"
                            class="continue-question mt-4 hidden rounded-lg bg-indigo-600 px-4 py-2 font-semibold text-white hover:bg-indigo-700">
                            Continue
                        </button>
                    </div>
                    <div class="answer-feedback-error mt-3 hidden rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800" role="alert">
                        <p>We couldn’t check your answer. Please try again.</p>
                        <button type="button" class="retry-answer mt-2 font-semibold underline">Retry</button>
                    </div>
                </div>
            @endforeach
        </div>

        <div id="submission-error" class="hidden rounded-lg border border-red-200 bg-red-50 p-6 text-center text-red-800" role="alert">
            <h2 class="text-lg font-bold">We couldn’t submit your quiz</h2>
            <p id="submission-error-message" class="mt-2"></p>
            <a href="{{ route('student.dashboard') }}"
                class="mt-4 inline-block rounded-lg bg-indigo-600 px-6 py-3 font-semibold text-white hover:bg-indigo-700 transition">
                Return to Dashboard
            </a>
        </div>
    </div>

    <!-- JavaScript Game Logic -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const slides = document.querySelectorAll('.question-slide');
            const totalQuestions = slides.length;
            let currentIndex = 0;
            const answers = {};

            const progressBar = document.getElementById('progress-bar');
            const progressText = document.getElementById('progress-text');
            const climberIcon = document.getElementById('climber-icon');
            const counter = document.getElementById('question-counter');
            const timer = document.getElementById('question-timer');
            const questionTimeLimit = 10;
            const timeoutAnswer = @json(\App\Http\Controllers\StudentQuizController::TIMEOUT_ANSWER);
            let timerInterval = null;
            let remainingSeconds = questionTimeLimit;

            function updateProgress() {
                let percentage = Math.round((currentIndex / totalQuestions) * 100);
                progressBar.style.width = percentage + '%';
                progressText.innerText = percentage + '%';
                climberIcon.style.left = percentage + '%';
            }

            function renderTimer() {
                timer.textContent = `00:${String(remainingSeconds).padStart(2, '0')}`;
                timer.classList.toggle('bg-red-100', remainingSeconds <= 3);
                timer.classList.toggle('text-red-800', remainingSeconds <= 3);
                timer.classList.toggle('bg-indigo-100', remainingSeconds > 3);
                timer.classList.toggle('text-indigo-800', remainingSeconds > 3);
            }

            function stopTimer() {
                if (timerInterval !== null) {
                    clearInterval(timerInterval);
                    timerInterval = null;
                }
            }

            function startTimer() {
                remainingSeconds = questionTimeLimit;
                renderTimer();
                timerInterval = setInterval(() => {
                    remainingSeconds--;
                    renderTimer();

                    if (remainingSeconds <= 0) {
                        stopTimer();
                        const slide = slides[currentIndex];
                        answers[slide.dataset.questionId] = timeoutAnswer;
                        checkAnswer(slide, null, true);
                    }
                }, 1000);
            }

            function showSubmissionError(message) {
                document.getElementById('submission-error-message').innerText = message;
                document.getElementById('submission-error').classList.remove('hidden');
            }

            function advanceQuestion(slide) {
                slide.classList.add('hidden');
                currentIndex++;

                if (currentIndex < totalQuestions) {
                    slides[currentIndex].classList.remove('hidden');
                    counter.innerText = `Question ${currentIndex + 1} of ${totalQuestions}`;
                    updateProgress();
                    startTimer();
                } else {
                    updateProgress();
                    document.getElementById('quiz-box').classList.add('hidden');
                    fetch("{{ route('student.quiz.submit', $session->id) }}", {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({ answers: answers })
                    }).then(async response => {
                        const data = await response.json();
                        if (!response.ok || !data.success) {
                            throw new Error(data.message || 'Could not submit quiz answers.');
                        }

                        window.location.href = data.redirect_url;
                    }).catch(error => {
                        showSubmissionError(error.message);
                    });
                }
            }

            function checkAnswer(slide, selectedOption, timedOut) {
                const buttons = Array.from(slide.querySelectorAll('.option-btn'));
                const feedback = slide.querySelector('.answer-feedback');
                const feedbackError = slide.querySelector('.answer-feedback-error');

                buttons.forEach(button => button.disabled = true);
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
                        answer: selectedOption,
                        timed_out: timedOut
                    })
                }).then(async response => {
                    const data = await response.json();
                    if (!response.ok || !data.success) {
                        throw new Error(data.message || 'Could not check this answer.');
                    }

                    feedback.classList.remove('hidden', 'border-green-200', 'bg-green-50', 'border-red-200', 'bg-red-50');
                    const feedbackStatus = feedback.querySelector('.feedback-status');
                    const feedbackCorrectAnswer = feedback.querySelector('.feedback-correct-answer');
                    const feedbackExplanation = feedback.querySelector('.feedback-explanation');

                    if (data.is_correct) {
                        feedback.classList.add('border-green-200', 'bg-green-50');
                        feedbackStatus.className = 'feedback-status font-bold text-green-800';
                        feedbackStatus.textContent = 'Correct!';
                        if (selectedOption !== null) {
                            const selectedButton = buttons.find(button => button.dataset.option === selectedOption);
                            selectedButton?.classList.add('bg-green-100', 'border-green-500', 'text-green-800');
                        }
                    } else {
                        feedback.classList.add('border-red-200', 'bg-red-50');
                        feedbackStatus.className = 'feedback-status font-bold text-red-800';
                        feedbackStatus.textContent = timedOut ? 'Time expired. Your answer is incorrect.' : 'Incorrect.';
                        feedbackCorrectAnswer.querySelector('.feedback-correct-answer-text').textContent = data.correct_answer;
                        feedbackCorrectAnswer.classList.remove('hidden');
                        feedbackExplanation.querySelector('.feedback-explanation-text').textContent = data.explanation || 'No explanation was provided.';
                        feedbackExplanation.classList.remove('hidden');

                        if (selectedOption !== null) {
                            const selectedButton = buttons.find(button => button.dataset.option === selectedOption);
                            selectedButton?.classList.add('bg-red-100', 'border-red-500', 'text-red-800');
                        }

                        const correctButton = buttons.find(button => button.dataset.option.trim() === String(data.correct_answer).trim());
                        correctButton?.classList.add('bg-green-100', 'border-green-500', 'text-green-800');
                    }

                    const continueButton = feedback.querySelector('.continue-question');
                    continueButton.classList.remove('hidden');
                }).catch(() => {
                    feedbackError.classList.remove('hidden');
                });
            }

            slides.forEach((slide) => {
                const buttons = slide.querySelectorAll('.option-btn');
                const retryButton = slide.querySelector('.retry-answer');
                const continueButton = slide.querySelector('.continue-question');

                buttons.forEach(button => {
                    button.addEventListener('click', function() {
                        const selectedOption = this.getAttribute('data-option');
                        answers[slide.dataset.questionId] = selectedOption;
                        stopTimer();
                        checkAnswer(slide, selectedOption, false);
                    });
                });

                retryButton.addEventListener('click', () => {
                    const answer = answers[slide.dataset.questionId];
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
