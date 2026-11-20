<?php

use App\Exceptions\GroqProviderException;
use App\Models\QuizSession;
use App\Models\User;
use App\Services\GeminiQuizService;
use App\Services\QuizGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('student document provider failures log only safe stage and text metrics', function () {
    Storage::fake('local');
    $student = User::factory()->create(['is_whitelisted' => true]);
    $logger = Mockery::mock();
    $logger->shouldReceive('error')->once()->withArgs(function ($message, $context) {
        expect($message)->toBe('Student PDF quiz generation failed.')
            ->and($context)->toHaveKeys([
                'provider',
                'failure_stage',
                'exception_class',
                'http_status',
                'provider_error_code',
                'extracted_text_empty',
                'extracted_text_character_count',
                'request_id',
            ])
            ->and($context['provider'])->toBe('groq')
            ->and($context['failure_stage'])->toBe('quiz_generation')
            ->and($context['exception_class'])->toBe(GroqProviderException::class)
            ->and($context['http_status'])->toBe(429)
            ->and($context['provider_error_code'])->toBe('RATE_LIMITED')
            ->and($context['extracted_text_empty'])->toBeFalse()
            ->and($context['extracted_text_character_count'])->toBe(mb_strlen('Extracted private course text.'))
            ->and($context['request_id'])->toBeString()
            ->and(json_encode($context))->not->toContain('Extracted private course text.');

        return true;
    });
    Log::shouldReceive('channel')->once()->with('stderr')->andReturn($logger);

    $this->mock(GeminiQuizService::class, function ($mock) {
        $mock->shouldReceive('extractPdfText')->once()->andReturn('Extracted private course text.');
    });
    $this->mock(QuizGeneratorService::class, function ($mock) {
        $mock->shouldReceive('generateDocumentQuizFromText')
            ->once()
            ->andThrow(new GroqProviderException(429, 'RATE_LIMITED'));
    });

    $this->actingAs($student)
        ->withHeader('Accept', 'application/json')
        ->post(route('student.quiz.generate.document'), [
            'pdf' => UploadedFile::fake()->createWithContent('course.pdf', "%PDF-1.4\n%%EOF"),
            'title' => 'Private title',
            'language' => 'PHP',
            'quiz_format' => 'multiple_choice',
        ])
        ->assertStatus(502)
        ->assertJsonPath('message', 'Quiz generation is temporarily unavailable. Please try again later.');

    expect(QuizSession::query()->count())->toBe(0);
});

test('empty extracted text reports the extraction stage without logging document content', function () {
    Storage::fake('local');
    $student = User::factory()->create(['is_whitelisted' => true]);
    $logger = Mockery::mock();
    $logger->shouldReceive('error')->once()->withArgs(function ($message, $context) {
        expect($context['provider'])->toBe('gemini')
            ->and($context['failure_stage'])->toBe('pdf_extraction')
            ->and($context['extracted_text_empty'])->toBeTrue()
            ->and($context['extracted_text_character_count'])->toBe(0);

        return true;
    });
    Log::shouldReceive('channel')->once()->with('stderr')->andReturn($logger);

    $this->mock(GeminiQuizService::class, function ($mock) {
        $mock->shouldReceive('extractPdfText')->once()->andReturn('');
    });
    $this->mock(QuizGeneratorService::class, function ($mock) {
        $mock->shouldNotReceive('generateDocumentQuizFromText');
    });

    $this->actingAs($student)
        ->withHeader('Accept', 'application/json')
        ->post(route('student.quiz.generate.document'), [
            'pdf' => UploadedFile::fake()->createWithContent('scanned.pdf', "%PDF-1.4\n%%EOF"),
            'title' => 'Exam review',
            'language' => 'PHP',
            'quiz_format' => 'multiple_choice',
        ])
        ->assertStatus(502)
        ->assertJsonPath('message', 'Text could not be extracted from this PDF. Try a searchable PDF and upload it again.');
});
