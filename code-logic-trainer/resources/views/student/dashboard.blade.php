<x-app-layout>

    <style>
        [x-cloak] {
            display: none !important;
        }
    </style>

    <div class="max-w-3xl mx-auto py-10 px-6 bg-white shadow rounded-lg">
        <div class="mb-6 rounded-lg border border-indigo-100 bg-indigo-50 p-5 text-left">
            <h3 class="text-lg font-bold text-indigo-900">Want to practice writing code?</h3>
            <p class="mt-1 text-sm text-indigo-800">Solve PHP challenges, run automated tests, and unlock badges.</p>
            <a href="{{ route('student.challenges.index') }}"
                class="mt-3 inline-block rounded-md bg-indigo-600 px-4 py-2 font-semibold text-white hover:bg-indigo-700">
                Explore Coding Challenges
            </a>
        </div>

        {{-- Generator --}}
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

                    const data = await response.json();

                    if (!response.ok || !data.success) {
                        throw new Error(data.message || `Request failed (${response.status})`);
                    }

                    if (typeof data.redirect_url !== 'string' || !data.redirect_url.trim()) {
                        throw new Error('Server did not return a redirect URL.');
                    }

                    const redirectUrl = new URL(data.redirect_url, window.location.origin);
                    this.stopProgress();
                    this.progress = 100;
                    this.statusMessage = 'Summit path ready! Loading your quiz...';
                    window.location.href = redirectUrl.pathname + redirectUrl.search + redirectUrl.hash;
                } catch (err) {
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
            <form @submit.prevent="generateQuiz" class="space-y-4 max-w-md mx-auto text-left" x-show="!isGenerating">
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
                    <input type="text" id="topic" name="topic" x-model="topic" placeholder="e.g. Arrays, Loops"
                        required class="w-full mt-1 border-gray-300 rounded-md shadow-sm">
                </div>

                <button type="submit" :disabled="!topic.trim() || isGenerating"
                    class="w-full bg-indigo-600 text-white py-2 px-4 rounded-md hover:bg-indigo-700 font-semibold transition disabled:opacity-50">
                    Generate AI Quiz
                </button>

                <p x-show="errorMessage" x-text="errorMessage" class="text-sm text-red-600 mt-2" x-cloak></p>
            </form>

            {{-- Progress --}}
            <div x-show="isGenerating" x-cloak class="space-y-6 py-6 max-w-md mx-auto text-center">
                <div class="space-y-2">
                    <p class="text-lg font-semibold text-indigo-600 animate-pulse" x-text="statusMessage"></p>
                    <p class="text-sm text-gray-500">Please stay on this page while Gemini constructs your code trail...
                    </p>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-4 overflow-hidden shadow-inner">
                    <div class="bg-indigo-600 h-4 rounded-full transition-all duration-700 ease-out"
                        :style="'width: ' + Math.round(progress) + '%'">
                    </div>
                </div>
                <p class="text-xs font-mono text-gray-400" x-text="Math.round(progress) + '% completed'"></p>
            </div>
        </div>
    </div>
</x-app-layout>
