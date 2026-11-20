<x-app-layout>
    <div class="auth-shell relative isolate min-h-[calc(100vh-4rem)] overflow-hidden px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
        <div class="ambient-orb ambient-orb--violet" aria-hidden="true"></div>
        <div class="ambient-orb ambient-orb--indigo" aria-hidden="true"></div>
        <div class="ambient-orb ambient-orb--cyan" aria-hidden="true"></div>

        <main class="relative z-10 mx-auto max-w-3xl space-y-6">
            <section class="app-glass-panel rounded-2xl border p-4" aria-label="Live quiz progress">
                <div class="mb-2 flex items-center justify-between gap-3">
                    <span class="truncate text-sm font-bold text-violet-200">{{ $session->quiz->title }}</span>
                    <span id="progress-text" class="text-sm font-bold text-[#F8FAFC]">0%</span>
                </div>
                <div class="h-3 w-full overflow-hidden rounded-full bg-white/10">
                    <div id="progress-bar" class="h-full w-0 bg-gradient-to-r from-violet-700 to-violet-400 transition-all duration-500"></div>
                </div>
            </section>

            <section class="app-glass-panel rounded-2xl border p-5 sm:p-7" id="quiz-box">
                <div class="mb-5 flex items-center justify-between gap-3 border-b border-white/10 pb-4">
                    <h1 class="text-xl font-bold text-[#F8FAFC]">Live quiz</h1>
                    <span class="text-sm font-semibold text-[#C8BED6]" id="question-counter">Question 1 of {{ count($session->quiz->questions) }}</span>
                </div>

                @foreach ($session->quiz->questions as $index => $question)
                    <div class="question-slide {{ $index > 0 ? 'hidden' : '' }}" data-index="{{ $index }}"
                        data-question-id="{{ $question->id }}" data-question-type="{{ $question->question_type ?? 'multiple_choice' }}">
                        <pre class="mb-6 overflow-x-auto whitespace-pre-wrap rounded-xl border border-white/10 bg-[#0F0B1A]/75 p-4 font-mono text-sm text-[#E8E2F0]">{{ $question->question_text }}</pre>

                        @if (($question->question_type ?? 'multiple_choice') === 'multiple_choice')
                            <div class="grid grid-cols-1 gap-3">
                                @foreach ($question->options ?? [] as $option)
                                    <button type="button" class="option-btn app-secondary-button min-h-12 w-full px-4 py-3 text-left text-sm font-medium text-[#E8E2F0] hover:border-violet-300/40 hover:bg-violet-300/[0.08]" data-option="{{ $option }}">
                                        {{ $option }}
                                    </button>
                                @endforeach
                            </div>
                        @else
                            <div class="space-y-3">
                                <label for="answer-{{ $question->id }}" class="auth-label">Your answer <span class="font-normal text-[#A59EB8]">(optional)</span></label>
                                <input id="answer-{{ $question->id }}" type="text" maxlength="1000" class="auth-input fill-answer" placeholder="Type a short answer" autocomplete="off">
                                <button type="button" class="auth-primary-button submit-fill-answer inline-flex min-h-11 items-center justify-center px-5 text-sm font-semibold">Submit answer</button>
                            </div>
                        @endif
                    </div>
                @endforeach
            </section>

            <div id="submission-error" class="app-glass-panel hidden rounded-2xl border border-rose-300/25 p-6 text-center text-rose-100" role="alert">
                <h2 class="text-lg font-bold text-[#F8FAFC]">We couldn’t submit your quiz</h2>
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
            const counter = document.getElementById('question-counter');
            const progressBar = document.getElementById('progress-bar');
            const progressText = document.getElementById('progress-text');

            function updateProgress() {
                const percentage = Math.round((currentIndex / totalQuestions) * 100);
                progressBar.style.width = percentage + '%';
                progressText.textContent = percentage + '%';
            }

            function submitQuiz() {
                updateProgress();
                document.getElementById('quiz-box').classList.add('hidden');
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
                } else {
                    submitQuiz();
                }
            }

            function answerQuestion(slide, answer, button) {
                const controls = slide.querySelectorAll('.option-btn, .fill-answer, .submit-fill-answer');
                controls.forEach(control => control.disabled = true);
                answers[slide.dataset.questionId] = answer;
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
        });
    </script>
</x-app-layout>
