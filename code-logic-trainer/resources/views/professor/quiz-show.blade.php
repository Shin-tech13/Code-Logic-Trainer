<x-app-layout>
    <div class="max-w-4xl mx-auto py-10 px-6 space-y-6">

        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-bold text-gray-800">{{ $quiz->title }}</h2>
                @if ($quiz->description)
                    <p class="text-gray-500">{{ $quiz->description }}</p>
                @endif
                <p class="text-sm text-gray-400 mt-1">{{ $quiz->questions->count() }} questions</p>
            </div>
            <div class="flex gap-3">
                <a href="{{ route('professor.dashboard') }}" class="text-gray-600 hover:text-gray-900 text-sm">Back</a>
                <form method="POST" action="{{ route('professor.sessions.launch', $quiz->id) }}">
                    @csrf
                    <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700 font-semibold">
                        Start Live Session
                    </button>
                </form>
            </div>
        </div>

        <div class="space-y-4">
            @foreach ($quiz->questions as $index => $question)
                <div class="bg-white shadow rounded-lg p-5">
                    <p class="font-semibold text-gray-800 mb-2">Q{{ $index + 1 }}:</p>
                    <pre class="bg-gray-900 text-green-400 p-3 rounded mb-4 text-sm font-mono whitespace-pre-wrap">{{ $question->question_text }}</pre>

                    <div class="space-y-2 mb-3">
                        @foreach ($question->options as $option)
                            <div class="p-2 border rounded {{ $option === $question->correct_answer ? 'border-green-400 bg-green-50 text-green-800' : 'border-gray-200 text-gray-600' }}">
                                {{ $option }}
                            </div>
                        @endforeach
                    </div>

                    <div class="text-sm text-gray-600 bg-blue-50 border border-blue-200 p-3 rounded">
                        <strong class="text-blue-900">Explanation:</strong> {{ $question->explanation }}
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</x-app-layout>
