<x-app-layout>
    <div class="max-w-3xl mx-auto py-10 px-6">

        <div class="bg-white shadow rounded-lg p-4 mb-6">
            <div class="flex justify-between items-center mb-2">
                <span class="text-sm font-bold text-indigo-600">{{ $session->quiz->title }}</span>
                <span id="progress-text" class="text-sm font-bold text-gray-700">0%</span>
            </div>
            <div class="relative w-full bg-gray-200 h-6 rounded-full overflow-hidden shadow-inner">
                <div id="progress-bar" class="bg-gradient-to-r from-indigo-500 to-green-500 h-full w-0 transition-all duration-500"></div>
            </div>
        </div>

        <div class="bg-white shadow rounded-lg p-6" id="quiz-box">
            <div class="flex justify-between items-center mb-4 border-b pb-3">
                <h2 class="text-lg font-bold text-gray-800">Live Quiz</h2>
                <span class="text-sm text-gray-500 font-semibold" id="question-counter">
                    Question 1 of {{ count($session->quiz->questions) }}
                </span>
            </div>

            @foreach ($session->quiz->questions as $index => $question)
                <div class="question-slide {{ $index > 0 ? 'hidden' : '' }}" data-index="{{ $index }}"
                    data-question-id="{{ $question->id }}">
                    <pre class="bg-gray-900 text-green-400 p-4 rounded-lg mb-6 text-sm font-mono whitespace-pre-wrap overflow-x-auto shadow-inner">{{ $question->question_text }}</pre>

                    <div class="grid grid-cols-1 gap-3 mb-4">
                        @foreach ($question->options as $option)
                            <button type="button"
                                class="option-btn text-left p-4 border-2 border-gray-200 rounded-lg bg-white hover:bg-indigo-50 hover:border-indigo-300 font-medium text-gray-700 transition"
                                data-option="{{ $option }}">
                                {{ $option }}
                            </button>
                        @endforeach
                    </div>

                </div>
            @endforeach
        </div>

        <div id="submission-error" class="hidden rounded-lg border border-red-200 bg-red-50 p-6 text-center text-red-800" role="alert">
            <h2 class="text-lg font-bold">We couldn’t submit your quiz</h2>
            <p id="submission-error-message" class="mt-2"></p>
            <a href="{{ route('student.live.lobby', $session->id) }}"
                class="mt-4 inline-block rounded-lg bg-indigo-600 px-6 py-3 font-semibold text-white hover:bg-indigo-700">
                Return to Quiz Lobby
            </a>
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
                    const pct = Math.round((currentIndex / totalQuestions) * 100);
                    progressBar.style.width = pct + '%';
                    progressText.innerText = pct + '%';
                }

                slides.forEach((slide) => {
                    const buttons = slide.querySelectorAll('.option-btn');

                    buttons.forEach(button => {
                        button.addEventListener('click', function() {
                            buttons.forEach(btn => btn.disabled = true);

                            answers[slide.dataset.questionId] = this.getAttribute('data-option');
                            this.classList.add('bg-indigo-100', 'border-indigo-500', 'text-indigo-800');

                            setTimeout(() => {
                                slide.classList.add('hidden');
                                currentIndex++;
                                if (currentIndex < totalQuestions) {
                                    slides[currentIndex].classList.remove('hidden');
                                    counter.innerText = `Question ${currentIndex + 1} of ${totalQuestions}`;
                                    updateProgress();
                                } else {
                                    updateProgress();
                                    document.getElementById('quiz-box').classList.add('hidden');
                                    fetch("{{ route('student.live.submit', $session->id) }}", {
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
                                        document.getElementById('submission-error-message').innerText = error.message;
                                        document.getElementById('submission-error').classList.remove('hidden');
                                    });
                                }
                            }, 3000);
                        });
                    });
                });

                updateProgress();
            });
        </script>
    </div>
</x-app-layout>
