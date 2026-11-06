<?php

use App\Services\QuizGeneratorService;
use Illuminate\Support\Facades\Http;

test('student quiz generation sends the configured Groq model', function () {
    config([
        'services.groq.api_key' => 'test-key',
        'services.groq.model' => 'openai/gpt-oss-120b',
    ]);

    $questions = [];
    for ($index = 1; $index <= 5; $index++) {
        $questions[] = [
            'question_text' => "What does this code return for case {$index}?",
            'options' => ['A. 1', 'B. 2', 'C. 3', 'D. 4'],
            'correct_answer' => 'A. 1',
            'explanation' => 'The code returns 1.',
        ];
    }

    Http::fake([
        'api.groq.com/openai/v1/chat/completions' => Http::response([
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode(['questions' => $questions]),
                    ],
                ],
            ],
        ]),
    ]);

    $questions = app(QuizGeneratorService::class)->generateStudentQuiz('PHP', 'arrays', 5);

    expect($questions)->toHaveCount(5)
        ->and($questions[0]['correct_answer'])->toBe('A. 1');

    Http::assertSent(fn ($request) => $request['model'] === 'openai/gpt-oss-120b');
    Http::assertSent(fn ($request) => $request['response_format'] === ['type' => 'json_object']);
});

test('student quiz generation accepts a fenced JSON object and validates the question schema', function () {
    config([
        'services.groq.api_key' => 'test-key',
        'services.groq.model' => 'openai/gpt-oss-120b',
    ]);

    $questions = [];
    for ($index = 1; $index <= 5; $index++) {
        $questions[] = [
            'question_text' => "Question {$index}",
            'options' => ['A. First', 'B. Second', 'C. Third', 'D. Fourth'],
            'correct_answer' => 'B. Second',
            'explanation' => 'Second is correct.',
        ];
    }

    Http::fake([
        'api.groq.com/openai/v1/chat/completions' => Http::response([
            'choices' => [[
                'message' => ['content' => "```json\n" . json_encode(['questions' => $questions]) . "\n```"],
            ]],
        ]),
    ]);

    expect(app(QuizGeneratorService::class)->generateStudentQuiz('PHP', 'arrays', 5))
        ->toHaveCount(5);
});

test('student quiz generation rejects malformed JSON question data', function () {
    config([
        'services.groq.api_key' => 'test-key',
        'services.groq.model' => 'openai/gpt-oss-120b',
    ]);

    $questions = array_fill(0, 5, [
        'question_text' => 'Incomplete question',
        'options' => ['A. One'],
    ]);

    Http::fake([
        'api.groq.com/openai/v1/chat/completions' => Http::response([
            'choices' => [[
                'message' => ['content' => json_encode(['questions' => $questions])],
            ]],
        ]),
    ]);

    expect(fn () => app(QuizGeneratorService::class)->generateStudentQuiz('PHP', 'arrays', 5))
        ->toThrow(RuntimeException::class, 'malformed question');
});

test('a rejected Groq model reports how to correct Render configuration', function () {
    config([
        'services.groq.api_key' => 'test-key',
        'services.groq.model' => 'llama-3.1-8b-instant',
    ]);

    Http::fake([
        'api.groq.com/openai/v1/chat/completions' => Http::response([
            'error' => [
                'message' => 'The model does not exist.',
                'code' => 'model_not_found',
            ],
        ], 404),
    ]);

    expect(fn () => app(QuizGeneratorService::class)->generateStudentQuiz('PHP', 'arrays', 5))
        ->toThrow(RuntimeException::class, 'Set GROQ_MODEL in Render');
});
