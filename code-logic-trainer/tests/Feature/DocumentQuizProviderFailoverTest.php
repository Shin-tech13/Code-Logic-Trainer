<?php

use App\Exceptions\GeminiProviderException;
use App\Exceptions\GroqProviderException;
use App\Exceptions\InvalidQuizOutputException;
use App\Services\DocumentQuizGenerationService;
use App\Services\QuizGeneratorService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    config([
        'services.gemini.api_key' => 'gemini-test-key',
        'services.gemini.model' => 'gemini-test-model',
        'services.gemini.api_url' => 'https://gemini.test/v1beta/models',
        'services.groq.api_key' => 'groq-test-key',
        'services.groq.model' => 'configured-groq-model',
    ]);
});

function documentQuizQuestion(): array
{
    return [
        'question_text' => 'Which value represents true?',
        'options' => ['A. True', 'B. False', 'C. Null', 'D. Empty'],
        'correct_answer' => 'A. True',
        'accepted_answers' => [],
        'explanation' => 'True is the boolean value.',
        'needs_review' => false,
    ];
}

function geminiQuizProviderResponse(string $text): array
{
    return [
        'candidates' => [[
            'content' => ['parts' => [['text' => $text]]],
            'finishReason' => 'STOP',
        ]],
    ];
}

test('transient Gemini failures retry with the configured delay hint then use Groq once', function () {
    $groqQuestion = documentQuizQuestion();
    $groqResponse = [
        'choices' => [[
            'finish_reason' => 'stop',
            'message' => [
                'content' => json_encode(['questions' => [$groqQuestion]]),
            ],
        ]],
    ];
    $sequence = Http::sequence()
        ->push(['error' => ['status' => 'UNAVAILABLE']], 503, ['Retry-After' => '0'])
        ->push(['error' => ['status' => 'UNAVAILABLE']], 503, ['Retry-After' => '0'])
        ->push(['error' => ['status' => 'UNAVAILABLE']], 503, ['Retry-After' => '31'])
        ->push($groqResponse);
    Http::fake(['*' => $sequence]);

    $logger = Mockery::mock();
    $logger->shouldReceive('warning')->once()->withArgs(function ($message, $context) {
        expect($message)->toBe('Gemini document quiz generation unavailable; attempting Groq fallback.')
            ->and($context['provider'])->toBe('gemini')
            ->and($context['provider_http_status'])->toBe(503)
            ->and($context['provider_error_code'])->toBe('UNAVAILABLE')
            ->and($context['request_id'])->toBe('request-correlation-test');

        return true;
    });
    Log::shouldReceive('channel')->once()->with('stderr')->andReturn($logger);

    $questions = app(DocumentQuizGenerationService::class)->generate(
        'Only the needed extracted lesson text.',
        1,
        'multiple_choice',
        'request-correlation-test',
    );

    expect($questions)->toHaveCount(1)
        ->and($questions[0]['correct_answer'])->toBe('A. True');

    $requests = Http::recorded()->pluck(0);
    expect($requests)->toHaveCount(4)
        ->and($requests[0]->url())->toStartWith('https://gemini.test/')
        ->and($requests[1]->url())->toStartWith('https://gemini.test/')
        ->and($requests[2]->url())->toStartWith('https://gemini.test/')
        ->and($requests[3]->url())->toBe('https://api.groq.com/openai/v1/chat/completions')
        ->and($requests[3]['model'])->toBe('configured-groq-model')
        ->and($requests[3]['response_format'])->toBe(['type' => 'json_object'])
        ->and($requests[3]['messages'][1]['content'])->toContain('Only the needed extracted lesson text.');
});

test('permanent Gemini errors do not fail over to Groq', function () {
    Http::fake(['*' => Http::response([
        'error' => ['status' => 'PERMISSION_DENIED', 'message' => 'not for logs'],
    ], 403)]);

    expect(fn () => app(DocumentQuizGenerationService::class)->generate(
        'Course text.',
        1,
        'multiple_choice',
        'request-correlation-test',
    ))->toThrow(GeminiProviderException::class);

    expect(Http::recorded())->toHaveCount(1);
});

test('Gemini network timeouts are retried before a single Groq fallback', function () {
    $geminiAttempts = 0;
    $groqResponse = [
        'choices' => [[
            'finish_reason' => 'stop',
            'message' => [
                'content' => json_encode(['questions' => [documentQuizQuestion()]]),
            ],
        ]],
    ];
    Http::fake(function ($request) use (&$geminiAttempts, $groqResponse) {
        if (str_starts_with($request->url(), 'https://gemini.test/')) {
            $geminiAttempts++;
            throw new ConnectionException('simulated timeout');
        }

        return Http::response($groqResponse);
    });

    $logger = Mockery::mock();
    $logger->shouldReceive('warning')->once()->withArgs(function ($message, $context) {
        return $message === 'Gemini document quiz generation unavailable; attempting Groq fallback.'
            && $context['provider_http_status'] === null
            && $context['provider_error_code'] === null;
    });
    Log::shouldReceive('channel')->once()->with('stderr')->andReturn($logger);

    $questions = app(DocumentQuizGenerationService::class)->generate(
        'Course text.',
        1,
        'multiple_choice',
        'request-correlation-test',
    );

    expect($questions)->toHaveCount(1)
        ->and($geminiAttempts)->toBe(3);
});

test('malformed Gemini output is rejected without fallback', function () {
    Http::fake([
        '*' => Http::response(geminiQuizProviderResponse('{"questions":[{"question_text":"incomplete"}]}')),
    ]);

    expect(fn () => app(DocumentQuizGenerationService::class)->generate(
        'Course text.',
        1,
        'multiple_choice',
        'request-correlation-test',
    ))->toThrow(InvalidQuizOutputException::class);

    expect(Http::recorded())->toHaveCount(1);
});

test('a failed Groq fallback returns a safe provider failure without another provider loop', function () {
    Http::fake([
        '*' => Http::sequence()
            ->push(['error' => ['status' => 'UNAVAILABLE']], 503, ['Retry-After' => '0'])
            ->push(['error' => ['status' => 'UNAVAILABLE']], 503, ['Retry-After' => '0'])
            ->push(['error' => ['status' => 'UNAVAILABLE']], 503, ['Retry-After' => '0'])
            ->push(['error' => ['code' => 'service_unavailable']], 503),
    ]);

    $logger = Mockery::mock();
    $logger->shouldReceive('warning')->once();
    Log::shouldReceive('channel')->once()->with('stderr')->andReturn($logger);

    try {
        app(DocumentQuizGenerationService::class)->generate(
            'Course text.',
            1,
            'multiple_choice',
            'request-correlation-test',
        );
        $this->fail('Expected both providers to fail.');
    } catch (GroqProviderException $exception) {
        expect($exception->providerName)->toBe('groq')
            ->and($exception->httpStatus)->toBe(503)
            ->and($exception->transient)->toBeTrue();
    }

    expect(Http::recorded())->toHaveCount(4);
});

test('Groq connection failures retain their underlying exception', function () {
    Http::fake(function (Request $request) {
        throw new ConnectionException('simulated connection failure');
    });

    try {
        app(QuizGeneratorService::class)->generateDocumentQuizFromText(
            'Course text.',
            1,
            'multiple_choice',
        );
        $this->fail('Expected Groq to fail with a connection exception.');
    } catch (GroqProviderException $exception) {
        expect($exception->getPrevious())->toBeInstanceOf(ConnectionException::class);
    }
});

test('an inconclusive local exam parse logs only a reason code and falls back to Groq', function () {
    config([
        'services.groq.api_key' => 'groq-test-key',
        'services.groq.model' => 'configured-groq-model',
    ]);
    $question = documentQuizQuestion();
    Http::fake([
        '*' => Http::response([
            'choices' => [[
                'finish_reason' => 'stop',
                'message' => [
                    'content' => json_encode(['questions' => [$question]]),
                ],
            ]],
        ]),
    ]);

    $logger = Mockery::mock();
    $logger->shouldReceive('warning')->once()->withArgs(function ($message, $context) {
        expect($message)->toBe('Local exam parsing rejected the document; using Groq generation.')
            ->and($context['provider'])->toBe('local_exam_parser')
            ->and($context['failure_stage'])->toBe('exam_parse_fallback')
            ->and($context['rejection_reason'])->toBe('missing_answer_key')
            ->and($context['extracted_text_character_count'])->toBeGreaterThan(0);

        return true;
    });
    Log::shouldReceive('channel')->once()->with('stderr')->andReturn($logger);

    $questions = app(QuizGeneratorService::class)->generateDocumentQuizFromText(
        "1. Question one?\nA. One\nB. Two\nC. Three\nD. Four\n2. Question two?\nA. One\nB. Two\nC. Three\nD. Four",
        1,
        'multiple_choice',
        'request-correlation-test',
    );

    expect($questions)->toHaveCount(1)
        ->and($questions[0]['correct_answer'])->toBe('A. True');

    Http::assertSent(fn ($request) => $request['model'] === 'configured-groq-model');
});
