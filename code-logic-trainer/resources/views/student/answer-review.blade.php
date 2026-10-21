<div class="mt-8 space-y-5 text-left">
    <h3 class="text-xl font-bold text-gray-800">Answer Review</h3>

    @foreach ($questions as $index => $question)
        @php
            $selectedAnswer = $answers[$question->id] ?? null;
            $isCorrect = $selectedAnswer === $question->correct_answer;
        @endphp

        <article class="rounded-lg border p-5 {{ $isCorrect ? 'border-green-200 bg-green-50' : 'border-red-200 bg-red-50' }}">
            <div class="flex items-center justify-between gap-3">
                <h4 class="font-semibold text-gray-900">Question {{ $index + 1 }}</h4>
                <span class="rounded-full px-3 py-1 text-sm font-semibold {{ $isCorrect ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                    {{ $isCorrect ? 'Correct' : 'Incorrect' }}
                </span>
            </div>
            <p class="mt-2 whitespace-pre-wrap text-gray-800">{{ $question->question_text }}</p>

            <p class="mt-3 {{ $isCorrect ? 'text-green-800' : 'text-red-800' }}">
                <span class="font-semibold">Your answer:</span>
                {{ $selectedAnswer ?? 'No answer submitted' }}
            </p>

            @unless ($isCorrect)
                <p class="mt-1 text-green-800">
                    <span class="font-semibold">Correct answer:</span> {{ $question->correct_answer }}
                </p>
            @endunless

            @if ($question->explanation)
                <div class="mt-3 rounded border border-blue-200 bg-blue-50 p-3 text-sm text-gray-700">
                    <span class="font-semibold text-blue-900">Explanation:</span>
                    {{ $question->explanation }}
                </div>
            @endif
        </article>
    @endforeach
</div>
