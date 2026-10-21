<x-app-layout>
    <div class="max-w-5xl mx-auto py-10 px-6 space-y-8">

        @if (session('status'))
            <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg">
                {{ session('status') }}
            </div>
        @endif

        {{-- PDF -> Quiz generator --}}
        <div class="bg-white shadow rounded-lg p-6"
            x-data="{
                title: '',
                description: '',
                count: 10,
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
                            this.statusMessage = 'Generating questions with Gemini AI...';
                        } else if (this.progress >= 40) {
                            this.statusMessage = 'Analyzing the PDF content...';
                        }
                    }, 400);
                },

                stopProgress() {
                    if (this._timer) { clearInterval(this._timer); this._timer = null; }
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
                        formData.append('count', this.count);
                        formData.append('_token', '{{ csrf_token() }}');

                        const response = await fetch('{{ route('professor.quizzes.generate') }}', {
                            method: 'POST',
                            headers: { 'Accept': 'application/json' },
                            body: formData,
                        });

                        const data = await response.json();
                        if (!response.ok || !data.success) {
                            throw new Error(data.message || `Request failed (${response.status})`);
                        }

                        this.stopProgress();
                        this.progress = 100;
                        this.statusMessage = 'Quiz ready! Redirecting...';
                        window.location.href = data.redirect_url;
                    } catch (err) {
                        this.stopProgress();
                        this.errorMessage = err.message || 'Something went wrong.';
                        this.isGenerating = false;
                        this.progress = 0;
                    }
                }
            }">

            <h2 class="text-xl font-bold text-gray-800 mb-1">Create Quiz from PDF</h2>
            <p class="text-sm text-gray-500 mb-6">Upload a PDF and let Gemini AI turn it into a multiple-choice quiz.</p>

            <form @submit.prevent="generateQuiz" class="space-y-4 max-w-lg" x-show="!isGenerating">
                <div>
                    <label class="block font-medium text-gray-700">Quiz Title</label>
                    <input type="text" x-model="title" required class="w-full mt-1 border-gray-300 rounded-md shadow-sm" placeholder="e.g. Data Structures Midterm">
                </div>

                <div>
                    <label class="block font-medium text-gray-700">Description (optional)</label>
                    <input type="text" x-model="description" class="w-full mt-1 border-gray-300 rounded-md shadow-sm" placeholder="Short description">
                </div>

                <div>
                    <label class="block font-medium text-gray-700">Number of Questions</label>
                    <input type="number" x-model.number="count" min="1" max="30" class="w-full mt-1 border-gray-300 rounded-md shadow-sm">
                </div>

                <div>
                    <label class="block font-medium text-gray-700">PDF File</label>
                    <input type="file" x-ref="pdf" accept="application/pdf" required class="w-full mt-1 border-gray-300 rounded-md shadow-sm">
                </div>

                <button type="submit"
                    class="w-full bg-indigo-600 text-white py-2 px-4 rounded-md hover:bg-indigo-700 font-semibold transition disabled:opacity-50">
                    Generate Quiz
                </button>

                <p x-show="errorMessage" x-text="errorMessage" x-cloak class="text-sm text-red-600 mt-2"></p>
            </form>

            {{-- Progress --}}
            <div x-show="isGenerating" x-cloak class="space-y-6 py-6 max-w-lg text-center">
                <p class="text-lg font-semibold text-indigo-600 animate-pulse" x-text="statusMessage"></p>
                <div class="w-full bg-gray-200 rounded-full h-4 overflow-hidden shadow-inner">
                    <div class="bg-indigo-600 h-4 rounded-full transition-all duration-700 ease-out"
                        :style="'width: ' + Math.round(progress) + '%'"></div>
                </div>
                <p class="text-xs font-mono text-gray-400" x-text="Math.round(progress) + '% completed'"></p>
            </div>
        </div>

        {{-- Existing quizzes --}}
        <div class="bg-white shadow rounded-lg p-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Your Quizzes</h2>

            @if ($quizzes->isEmpty())
                <p class="text-gray-500">No quizzes yet. Upload a PDF above to create your first quiz.</p>
            @else
                <div class="divide-y divide-gray-100">
                    @foreach ($quizzes as $quiz)
                        <div class="py-4 flex items-center justify-between">
                            <div>
                                <a href="{{ route('professor.quizzes.show', $quiz->id) }}"
                                    class="text-lg font-semibold text-indigo-600 hover:underline">
                                    {{ $quiz->title }}
                                </a>
                                <p class="text-sm text-gray-500">
                                    {{ $quiz->questions_count }} question(s) · {{ $quiz->created_at->diffForHumans() }}
                                </p>
                            </div>
                            <div class="flex items-center gap-3">
                                <form method="POST" action="{{ route('professor.sessions.launch', $quiz->id) }}">
                                    @csrf
                                    <button type="submit"
                                        class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700 text-sm font-semibold">
                                        Start Live Session
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('professor.quizzes.destroy', $quiz->id) }}"
                                    onsubmit="return confirm('Delete this quiz?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:text-red-800 text-sm font-semibold">
                                        Delete
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
