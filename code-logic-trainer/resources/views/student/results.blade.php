<x-app-layout>
    <div class="mx-auto max-w-3xl space-y-6 px-6 py-10">
        <div class="rounded-lg bg-white p-8 text-center shadow">
            <h2 class="mb-2 text-3xl font-bold text-indigo-600">Quiz Complete!</h2>
            <p class="mb-6 text-gray-600">{{ $session->language }}: {{ $session->topic }}</p>
            <div class="mb-6 text-5xl font-extrabold text-green-600">
                Score: {{ $session->score }} / {{ $session->questions->count() }}
            </div>
            <a href="{{ route('student.dashboard') }}"
                class="inline-block rounded-lg bg-indigo-600 px-6 py-3 font-semibold text-white hover:bg-indigo-700">
                Return to Dashboard
            </a>
        </div>

        @include('student.answer-review', [
            'questions' => $session->questions,
            'answers' => $session->answers ?? [],
        ])
    </div>
</x-app-layout>
