<?php

use App\Exceptions\GeminiProviderException;
use App\Services\GeminiQuizService;
use Illuminate\Support\Facades\Http;

function fakeGeminiQuizResponse(array $questions): void
{
    Http::fake([
        '*' => Http::response([
            'candidates' => [[
                'content' => [
                    'parts' => [[
                        'text' => json_encode($questions),
                    ]],
                ],
                'finishReason' => 'STOP',
            ]],
        ]),
    ]);
}

beforeEach(function () {
    config([
        'services.gemini.api_key' => 'test-key',
        'services.gemini.model' => 'gemini-test',
        'services.gemini.api_url' => 'https://gemini.test/v1beta/models',
    ]);
});

test('document quiz generation supports fill-in-the-blank and limits positive counts', function () {
    fakeGeminiQuizResponse([
        [
            'question_text' => 'The PHP loop keyword is _____.',
            'options' => [],
            'correct_answer' => 'foreach',
            'accepted_answers' => ['FOREACH'],
            'explanation' => 'foreach iterates over arrays and objects.',
            'needs_review' => false,
        ],
        [
            'question_text' => 'A duplicate question uses _____.',
            'options' => [],
            'correct_answer' => 'while',
            'accepted_answers' => [],
            'explanation' => 'while repeats while a condition is true.',
            'needs_review' => true,
        ],
        [
            'question_text' => 'This extra item should be trimmed _____.',
            'options' => [],
            'correct_answer' => 'do while',
            'accepted_answers' => [],
            'explanation' => 'It is beyond the requested question count.',
            'needs_review' => false,
        ],
    ]);

    $questions = app(GeminiQuizService::class)->generateQuizFromText(
        'PHP loops',
        'Loops',
        2,
        'fill_in_the_blank',
    );

    expect($questions)->toHaveCount(2)
        ->and($questions[0]['question_type'])->toBe('fill_in_the_blank')
        ->and($questions[0]['options'])->toBe([])
        ->and($questions[0]['accepted_answers'])->toBe(['foreach', 'FOREACH']);

    Http::assertSent(fn ($request) => str_contains($request['contents'][0]['parts'][0]['text'], '"options": []')
        && str_contains($request['contents'][0]['parts'][0]['text'], 'answer key')
        && $request['generationConfig']['maxOutputTokens'] === 8192);
});

test('automatic count drops duplicate and malformed model questions', function () {
    fakeGeminiQuizResponse([
        [
            'question_text' => '  What is 1 + 1? ',
            'options' => ['A. 2', 'B. 3', 'C. 4', 'D. 5'],
            'correct_answer' => 'A',
            'accepted_answers' => [],
            'explanation' => 'The sum is two.',
            'needs_review' => false,
        ],
        [
            'question_text' => 'What is 1 + 1?',
            'options' => ['A. 2', 'B. 3', 'C. 4', 'D. 5'],
            'correct_answer' => 'A. 2',
            'accepted_answers' => [],
            'explanation' => 'Duplicate.',
            'needs_review' => false,
        ],
        [
            'question_text' => 'Malformed answer reference?',
            'options' => ['A. One', 'B. Two', 'C. Three', 'D. Four'],
            'correct_answer' => 'E',
            'accepted_answers' => [],
            'explanation' => 'Invalid correct choice.',
            'needs_review' => false,
        ],
        [
            'question_text' => 'What is 2 + 2?',
            'options' => ['A. 4', 'B. 5', 'C. 6', 'D. 7'],
            'correct_answer' => 'A. 4',
            'accepted_answers' => [],
            'explanation' => 'The sum is four.',
            'needs_review' => false,
        ],
    ]);

    $questions = app(GeminiQuizService::class)->generateQuizFromText(
        'Basic arithmetic',
        'Arithmetic',
        null,
        'multiple_choice',
    );

    expect($questions)->toHaveCount(2)
        ->and($questions[0]['correct_answer'])->toBe('A. 2')
        ->and($questions[0]['needs_review'])->toBeFalse();
});

test('provider failures expose only the HTTP status and sanitized provider error code', function () {
    Http::fake([
        '*' => Http::response([
            'error' => [
                'code' => 429,
                'status' => 'RESOURCE_EXHAUSTED',
                'message' => 'sensitive document text must not be logged',
            ],
        ], 429),
    ]);

    try {
        app(GeminiQuizService::class)->generateQuizFromText(
            'Sensitive source material',
            'Test',
            1,
        );
        $this->fail('Expected the provider request to fail.');
    } catch (GeminiProviderException $exception) {
        expect($exception->httpStatus)->toBe(429)
            ->and($exception->providerErrorCode)->toBe('RESOURCE_EXHAUSTED')
            ->and($exception->getMessage())->not->toContain('sensitive');
    }
});
