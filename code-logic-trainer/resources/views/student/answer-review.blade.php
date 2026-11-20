<div class="mt-8 space-y-5 text-left">
    <h3 class="text-xl font-bold text-[#F8FAFC]">Answer review</h3>

    @foreach ($questions as $index => $question)
        @php
            $selectedAnswer = $answers[$question->id] ?? null;
            $timedOut = $selectedAnswer === \App\Http\Controllers\StudentQuizController::TIMEOUT_ANSWER;
            $isCorrect = ! $timedOut && $question->isCorrectAnswer($selectedAnswer);
            $isBlank = $selectedAnswer === null || trim((string) $selectedAnswer) === '';
        @endphp

        <article class="app-glass-panel rounded-2xl border p-5 {{ $isCorrect ? 'border-emerald-300/25 bg-emerald-300/[0.04]' : 'border-rose-300/20 bg-rose-300/[0.04]' }}">
            <div class="flex items-center justify-between gap-3">
                <h4 class="font-semibold text-[#F8FAFC]">Question {{ $index + 1 }}</h4>
                <span class="rounded-full border px-3 py-1 text-sm font-semibold {{ $isCorrect ? 'border-emerald-300/25 bg-emerald-300/10 text-emerald-100' : 'border-rose-300/25 bg-rose-300/10 text-rose-100' }}">
                    {{ $isCorrect ? 'Correct' : 'Incorrect' }}
                </span>
            </div>
            <p class="mt-2 whitespace-pre-wrap text-[#E8E2F0]">{{ $question->question_text }}</p>

            <p class="mt-3 {{ $isCorrect ? 'text-emerald-100' : 'text-rose-100' }}">
                <span class="font-semibold text-[#D8CFE8]">Your answer:</span>
                {{ $timedOut ? 'Time expired' : ($isBlank ? 'No answer submitted' : $selectedAnswer) }}
            </p>

            <p class="mt-1 text-emerald-100">
                <span class="font-semibold text-[#D8CFE8]">Correct answer:</span> {{ $question->correct_answer }}
            </p>

            <div class="mt-3 rounded-xl border border-white/10 bg-black/10 p-3 text-sm text-[#D8CFE8]">
                <span class="font-semibold text-violet-200">Explanation:</span>
                {{ filled($question->explanation) ? $question->explanation : 'No explanation provided.' }}
            </div>
        </article>
    @endforeach
</div>
