<?php

use App\Exceptions\GeminiProviderException;
use App\Models\Quiz;
use App\Models\UploadedFile as StoredUpload;
use App\Models\User;
use App\Services\GeminiQuizService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

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

    $this->mock(GeminiQuizService::class, function ($mock) use ($generatedQuestion) {
        $mock->shouldReceive('extractPdfText')->once()->andReturn('Course text.');
        $mock->shouldReceive('generateQuizFromText')
            ->once()
            ->with('Course text.', 'Array loops', null, 'fill_in_the_blank')
            ->andReturn([$generatedQuestion]);
    });

    $response = $this->actingAs($professor)
        ->withHeader('Accept', 'application/json')
        ->post(route('professor.quizzes.generate'), [
            'pdf' => UploadedFile::fake()->createWithContent('course.pdf', "%PDF-1.4\n%%EOF"),
            'title' => 'Array loops',
            'count' => '0',
            'quiz_format' => 'fill_in_the_blank',
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
    $logger->shouldReceive('error')
        ->once()
        ->with('PDF quiz generation failed.', [
            'exception_class' => GeminiProviderException::class,
            'provider_http_status' => 503,
            'provider_error_code' => 'SERVICE_UNAVAILABLE',
        ]);
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
        ])
        ->assertStatus(502)
        ->assertJsonPath('message', 'Quiz generation could not be completed. Check the PDF and try again later.');
});
