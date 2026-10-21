<x-app-layout>
    <div class="max-w-3xl mx-auto py-10 px-6 space-y-6">
        <div class="bg-white shadow rounded-lg p-8 text-center">
            <h2 class="text-3xl font-bold text-indigo-600 mb-2">Quiz Complete!</h2>
            <p class="text-gray-600 mb-6">{{ $session->quiz->title }}</p>
            <div class="text-5xl font-extrabold text-green-600 mb-6">Score: {{ $participant->score }} / {{ $session->quiz->questions->count() }}</div>

            <div class="flex justify-center gap-3">
                <a href="{{ route('student.dashboard') }}"
                    class="bg-gray-200 text-gray-800 px-6 py-2 rounded-md hover:bg-gray-300 font-semibold">
                    Practice
                </a>
                <a href="{{ route('student.live.join') }}"
                    class="bg-indigo-600 text-white px-6 py-2 rounded-md hover:bg-indigo-700 font-semibold">
                    Join Another Quiz
                </a>
            </div>
        </div>

        @include('student.answer-review', [
            'questions' => $session->quiz->questions,
            'answers' => $participant->answers ?? [],
        ])
    </div>
</x-app-layout>
