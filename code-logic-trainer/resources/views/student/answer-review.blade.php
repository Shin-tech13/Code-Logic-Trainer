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
            <x-quiz-content :content="$question->question_text" class="mt-3 text-[#E8E2F0]" />

            <div class="mt-4">
                <h5 class="mb-1 text-sm font-semibold text-[#D8CFE8]">Your answer:</h5>
                <div class="rounded-lg border border-white/10 bg-black/10 p-3 {{ $isCorrect ? 'text-emerald-100' : 'text-rose-100' }}">
                    <x-quiz-content :content="$timedOut ? 'Time expired' : ($isBlank ? 'No answer submitted' : $selectedAnswer)" />
                </div>
            </div>

            <div class="mt-3">
                <h5 class="mb-1 text-sm font-semibold text-[#D8CFE8]">Correct answer:</h5>
                <div class="rounded-lg border border-emerald-300/15 bg-emerald-300/[0.04] p-3 text-emerald-100">
                    <x-quiz-content :content="$question->correct_answer" />
                </div>
            </div>

            <div class="mt-3 rounded-xl border border-violet-300/15 bg-violet-300/[0.035] p-4 text-sm text-[#D8CFE8]">
                <h5 class="mb-1 font-semibold text-violet-200">Explanation:</h5>
                <x-quiz-content :content="filled($question->explanation) ? $question->explanation : 'No explanation provided.'" />
            </div>
        </article>
    @endforeach
</div>
