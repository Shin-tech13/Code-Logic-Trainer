<x-app-layout>
    <!-- Load Alpine.js via CDN (Fixes the undefined issue) -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <style>
        [x-cloak] {
            display: none !important;
        }
    </style>

    <div class="max-w-3xl mx-auto py-10 px-6 bg-white shadow rounded-lg">
        @if (isset($session) && $session)
            {{-- ===================== Quiz Display (Fallback if they visit dashboard directly) ===================== --}}
            <h2 class="text-xl font-bold mb-4 text-indigo-600">
                Quiz: {{ $session->language }} - {{ $session->topic }}
            </h2>

            <div class="mb-6">
                <a href="{{ route('student.play', $session->id) }}"
                    class="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700">
                    Play Latest Quiz
                </a>
            </div>

            <div id="quiz-container">
                @foreach ($session->questions as $index => $question)
                    <div class="question-card mb-6 p-4 border rounded-lg bg-gray-50 {{ $index > 0 ? 'hidden' : '' }}"
                        data-index="{{ $index }}">
                        <p class="font-semibold text-gray-800 mb-3">Q{{ $index + 1 }}:</p>

                        <pre class="bg-gray-900 text-green-400 p-3 rounded mb-4 text-sm font-mono whitespace-pre-wrap">{{ $question->question_text }}</pre>

                        <div class="space-y-2">
                            @foreach ($question->options as $option)
                                <label class="block p-3 border rounded bg-white cursor-default">
                                    <input type="radio" disabled class="mr-2"> {{ $option }}
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            {{-- ===================== Generator (Alpine.js inline) ===================== --}}
            <div class="text-center py-8" x-data="{
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

                        // LOGGING FOR DEBUGGING
                        console.log('Response from server:', data);

                        if (!response.ok || !data.success) {
                            throw new Error(data.message || `Request failed (${response.status})`);
                        }

                        this.stopProgress();
                        this.progress = 100;
                        this.statusMessage = 'Summit path ready! Loading your quiz...';

                        // LOGGING REDIRECT URL
                        console.log('Redirecting to:', data.redirect_url);

                        if (data.redirect_url) {
                            // Give it a brief moment for the user to see 100%
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

                <h2 class="text-2xl font-bold text-gray-800 mb-2">Welcome to your Code Logic Trainer!</h2>
                <p class="text-gray-600 mb-6">Choose your parameters and watch our AI build your mountain path!</p>

                {{-- Form --}}
                <form @submit.prevent="generateQuiz" class="space-y-4 max-w-md mx-auto text-left"
                    x-show="!isGenerating">
                    <div>
                        <label for="language" class="block font-medium text-gray-700">Programming Language</label>
                        <select id="language" name="language" x-model="language"
                            class="w-full mt-1 border-gray-300 rounded-md shadow-sm">
                            <option value="PHP">PHP</option>
                            <option value="JavaScript">JavaScript</option>
                            <option value="Python">Python</option>
                            <option value="Java">Java</option>
                        </select>
                    </div>

                    <div>
                        <label for="topic" class="block font-medium text-gray-700">Topic</label>
                        <input type="text" id="topic" name="topic" x-model="topic"
                            placeholder="e.g. Arrays, Loops, Object-Oriented Programming" required
                            class="w-full mt-1 border-gray-300 rounded-md shadow-sm">
                    </div>

                    <button type="submit" :disabled="!topic.trim() || isGenerating"
                        class="w-full bg-indigo-600 text-white py-2 px-4 rounded-md hover:bg-indigo-700 font-semibold transition disabled:opacity-50 disabled:cursor-not-allowed">
                        Generate AI Quiz
                    </button>

                    <p x-show="errorMessage" x-text="errorMessage" class="text-sm text-red-600 mt-2" x-cloak></p>
                </form>

                {{-- Progress Bar --}}
                <div x-show="isGenerating" x-cloak class="space-y-6 py-6 max-w-md mx-auto text-center">
                    <div class="space-y-2">
                        <p class="text-lg font-semibold text-indigo-600 animate-pulse" x-text="statusMessage"></p>
                        <p class="text-sm text-gray-500">Please stay on this page while Gemini constructs your code
                            trail...</p>
                    </div>

                    <div class="w-full bg-gray-200 rounded-full h-4 overflow-hidden shadow-inner">
                        <div class="bg-indigo-600 h-4 rounded-full transition-all duration-700 ease-out"
                            :style="'width: ' + Math.round(progress) + '%'"></div>
                    </div>

                    <p class="text-xs font-mono text-gray-400" x-text="Math.round(progress) + '% completed'"></p>
                </div>
            </div>
        @endif
    </div>
</x-app-layout>
