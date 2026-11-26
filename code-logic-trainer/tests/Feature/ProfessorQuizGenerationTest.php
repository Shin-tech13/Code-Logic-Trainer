<?php

use App\Exceptions\GeminiProviderException;
use App\Exceptions\InvalidQuizOutputException;
use App\Models\Quiz;
use App\Models\UploadedFile as StoredUpload;
use App\Models\User;
use App\Services\DocumentQuizGenerationService;
use App\Services\GeminiQuizService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('a professor can create a private source-backed fill-in-the-blank quiz with automatic count', function () {
    Storage::fake('local');
    $professor = User::factory()->create(['role' => 'professor']);
    $generatedQuestion = [
        'question_text' => 'A PHP array traversal keyword is _____.',
        'options' => [],
        'question_type' => 'fill_in_the_blank',
        'correct_answer' => 'foreach',
        'accepted_answers' => ['foreach'],
        'explanation' => 'foreach iterates over each array item.',
        'needs_review' => false,
    ];

    $this->mock(GeminiQuizService::class, function ($mock) {
        $mock->shouldReceive('extractPdfText')->once()->andReturn('Course text.');
    });
    $this->mock(DocumentQuizGenerationService::class, function ($mock) use ($generatedQuestion) {
        $mock->shouldReceive('generate')
            ->once()
            ->with('Course text.', null, 'fill_in_the_blank', Mockery::type('string'))
            ->andReturn([$generatedQuestion]);
    });

    $response = $this->actingAs($professor)
        ->withHeader('Accept', 'application/json')
        ->post(route('professor.quizzes.generate'), [
            'pdf' => UploadedFile::fake()->createWithContent('course.pdf', "%PDF-1.4\n%%EOF"),
            'title' => 'Array loops',
            'count' => '0',
            'quiz_format' => 'fill_in_the_blank',
            'idempotency_key' => (string) Str::uuid(),
        ]);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('question_count', 1)
        ->assertJsonPath('quiz_format', 'fill_in_the_blank');

    $quiz = Quiz::query()->firstOrFail();
    $upload = StoredUpload::query()->firstOrFail();
    expect($quiz->professor_id)->toBe($professor->id)
        ->and($quiz->source_file_id)->toBe($upload->id)
        ->and($quiz->quiz_format)->toBe('fill_in_the_blank')
        ->and($quiz->questions()->first()->question_type)->toBe('fill_in_the_blank')
        ->and($upload->professor_id)->toBe($professor->id)
        ->and($upload->status)->toBe('processed');

    Storage::disk('local')->assertExists($upload->file_path);
});

test('professors cannot view or delete another professors quiz', function () {
    $owner = User::factory()->create(['role' => 'professor']);
    $otherProfessor = User::factory()->create(['role' => 'professor']);
    $quiz = Quiz::create([
        'professor_id' => $owner->id,
        'title' => 'Owned quiz',
        'question_count' => 0,
        'status' => 'ready',
    ]);

    $this->actingAs($otherProfessor)
        ->get(route('professor.quizzes.show', $quiz))
        ->assertForbidden();

    $this->actingAs($otherProfessor)
        ->delete(route('professor.quizzes.destroy', $quiz))
        ->assertForbidden();

    expect($quiz->fresh())->not->toBeNull();
});

test('professor generation logs only safe provider diagnostics to stderr', function () {
    Storage::fake('local');
    $professor = User::factory()->create(['role' => 'professor']);
    $logger = Mockery::mock();
    $logger->shouldReceive('error')->once()->withArgs(function ($message, $context) {
        expect($message)->toBe('PDF quiz generation failed.')
            ->and($context)->toHaveKeys([
                'provider',
                'exception_class',
                'provider_http_status',
                'provider_error_code',
                'request_id',
            ])
            ->and($context['provider'])->toBe('gemini')
            ->and($context['exception_class'])->toBe(GeminiProviderException::class)
            ->and($context['provider_http_status'])->toBe(503)
            ->and($context['provider_error_code'])->toBe('SERVICE_UNAVAILABLE')
            ->and(Str::isUuid($context['request_id']))->toBeTrue();

        return true;
    });
    Log::shouldReceive('channel')->once()->with('stderr')->andReturn($logger);

    $this->mock(GeminiQuizService::class, function ($mock) {
        $mock->shouldReceive('extractPdfText')
            ->once()
            ->andThrow(new GeminiProviderException(503, 'SERVICE_UNAVAILABLE'));
    });

    $this->actingAs($professor)
        ->withHeader('Accept', 'application/json')
        ->post(route('professor.quizzes.generate'), [
            'pdf' => UploadedFile::fake()->createWithContent('course.pdf', "%PDF-1.4\n%%EOF"),
            'title' => 'Test quiz',
            'quiz_format' => 'multiple_choice',
            'idempotency_key' => (string) Str::uuid(),
        ])
        ->assertStatus(502)
        ->assertJsonPath('message', 'Quiz generation is temporarily unavailable. Please try again later.');
});

test('resubmitting the same idempotency key returns the existing quiz without generating another', function () {
    Storage::fake('local');
    $professor = User::factory()->create(['role' => 'professor']);
    $requestData = [
        'pdf' => UploadedFile::fake()->createWithContent('course.pdf', "%PDF-1.4\n%%EOF"),
        'title' => 'Idempotent quiz',
        'quiz_format' => 'multiple_choice',
        'idempotency_key' => (string) Str::uuid(),
        'seconds_per_question' => '105',
    ];
    $questions = [[
        'question_text' => 'Which value is true?',
        'options' => ['A. True', 'B. False', 'C. Null', 'D. Empty'],
        'question_type' => 'multiple_choice',
        'correct_answer' => 'A. True',
        'accepted_answers' => [],
        'explanation' => 'True is the boolean value.',
        'needs_review' => false,
    ]];

    $this->mock(GeminiQuizService::class, function ($mock) {
        $mock->shouldReceive('extractPdfText')->once()->andReturn('Course text.');
    });
    $this->mock(DocumentQuizGenerationService::class, function ($mock) use ($questions) {
        $mock->shouldReceive('generate')
            ->once()
            ->andReturn($questions);
    });

    $first = $this->actingAs($professor)
        ->withHeader('Accept', 'application/json')
        ->post(route('professor.quizzes.generate'), $requestData);
    $first->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('seconds_per_question', 100);

    $second = $this->actingAs($professor)
        ->withHeader('Accept', 'application/json')
        ->post(route('professor.quizzes.generate'), $requestData);
    $second->assertOk()
        ->assertExactJson($first->json());

    expect(Quiz::query()->where('professor_id', $professor->id)->count())->toBe(1)
        ->and(Quiz::query()->where('professor_id', $professor->id)->firstOrFail()->seconds_per_question)->toBe(100)
        ->and(StoredUpload::query()->where('professor_id', $professor->id)->count())->toBe(1);
});

test('invalid AI output is not persisted', function () {
    Storage::fake('local');
    $professor = User::factory()->create(['role' => 'professor']);

    $this->mock(GeminiQuizService::class, function ($mock) {
        $mock->shouldReceive('extractPdfText')->once()->andReturn('Course text.');
    });
    $this->mock(DocumentQuizGenerationService::class, function ($mock) {
        $mock->shouldReceive('generate')
            ->once()
            ->andThrow(new InvalidQuizOutputException('gemini'));
    });

    $this->actingAs($professor)
        ->withHeader('Accept', 'application/json')
        ->post(route('professor.quizzes.generate'), [
            'pdf' => UploadedFile::fake()->createWithContent('course.pdf', "%PDF-1.4\n%%EOF"),
            'title' => 'Malformed quiz',
            'quiz_format' => 'multiple_choice',
            'idempotency_key' => (string) Str::uuid(),
        ])
        ->assertStatus(502)
        ->assertJsonPath('message', 'The AI returned quiz data that could not be validated. Try a shorter or clearer PDF.');

    expect(Quiz::query()->count())->toBe(0);
});
