<?php

use App\Http\Controllers\StudentQuizController;
use App\Models\QuizSession;
use App\Models\UploadedFile as StoredUpload;
use App\Models\User;
use App\Services\GeminiQuizService;
use App\Services\QuizGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function createStudentQuizSession(User $student): QuizSession
{
    $session = QuizSession::create([
        'student_id' => $student->id,
        'language' => 'PHP',
        'topic' => 'Arrays',
    ]);

    $session->questions()->create([
        'question_text' => 'Which value is correct?',
        'options' => ['Correct', 'Wrong'],
        'correct_answer' => 'Correct',
        'explanation' => 'Secret explanation',
    ]);

    return $session;
}

test('a student can access their own quiz session', function () {
    $student = User::factory()->create(['is_whitelisted' => true]);
    $session = createStudentQuizSession($student);

    $this->actingAs($student)
        ->get(route('student.play', $session))
        ->assertOk()
        ->assertSee('00:20');
});

test('a student cannot access another students quiz session', function () {
    $owner = User::factory()->create(['is_whitelisted' => true]);
    $otherStudent = User::factory()->create(['is_whitelisted' => true]);
    $session = createStudentQuizSession($owner);

    $this->actingAs($otherStudent)
        ->get(route('student.play', $session))
        ->assertForbidden();
});

test('a student can submit a score for their own quiz session', function () {
    $student = User::factory()->create(['is_whitelisted' => true]);
    $session = createStudentQuizSession($student);
    $question = $session->questions()->first();

    $this->actingAs($student)
        ->postJson(route('student.quiz.submit', $session), [
            'answers' => [$question->id => 'Correct'],
            'score' => 99,
        ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('score', 1);

    expect($session->fresh()->score)->toBe(1)
        ->and($session->fresh()->status)->toBe('completed');
});

test('student quiz answer feedback is checked by the server after selection', function () {
    $student = User::factory()->create(['is_whitelisted' => true]);
    $session = createStudentQuizSession($student);
    $question = $session->questions()->first();

    $this->actingAs($student)
        ->get(route('student.play', $session))
        ->assertOk()
        ->assertDontSee('Secret explanation');

    $this->postJson(route('student.quiz.answer', $session), [
        'question_id' => $question->id,
        'answer' => 'Wrong',
        'timed_out' => false,
    ])
        ->assertOk()
        ->assertJsonPath('is_correct', false)
        ->assertJsonPath('correct_answer', 'Correct')
        ->assertJsonPath('explanation', 'Secret explanation');

    $this->postJson(route('student.quiz.answer', $session), [
        'question_id' => $question->id,
        'answer' => 'Correct',
        'timed_out' => false,
    ])
        ->assertOk()
        ->assertJsonPath('is_correct', true)
        ->assertJsonMissingPath('correct_answer')
        ->assertJsonMissingPath('explanation');
});

test('timed-out student quiz answers are incorrect and shown in review', function () {
    $student = User::factory()->create(['is_whitelisted' => true]);
    $session = createStudentQuizSession($student);
    $question = $session->questions()->first();

    $this->actingAs($student)
        ->postJson(route('student.quiz.answer', $session), [
            'question_id' => $question->id,
            'answer' => null,
            'timed_out' => true,
        ])
        ->assertOk()
        ->assertJsonPath('is_correct', false)
        ->assertJsonPath('correct_answer', 'Correct');

    $this->postJson(route('student.quiz.submit', $session), [
        'answers' => [$question->id => StudentQuizController::TIMEOUT_ANSWER],
    ])
        ->assertOk()
        ->assertJsonPath('score', 0);

    $this->get(route('student.results', $session))
        ->assertOk()
        ->assertSee('Time expired')
        ->assertSee('Correct answer:')
        ->assertSee('Secret explanation');
});

test('self-study answer matching ignores generated leading and trailing whitespace', function () {
    $student = User::factory()->create(['is_whitelisted' => true]);
    $session = createStudentQuizSession($student);
    $question = $session->questions()->first();
    $question->update([
        'options' => [' Correct ', ' Wrong '],
        'correct_answer' => ' Correct ',
    ]);

    $this->actingAs($student)
        ->postJson(route('student.quiz.submit', $session), [
            'answers' => [$question->id => 'Correct'],
        ])
        ->assertOk()
        ->assertJsonPath('score', 1);

    expect($session->fresh()->answers[$question->id])->toBe('Correct');
});

test('self-study results show the missed answer and explanation only after submission', function () {
    $student = User::factory()->create(['is_whitelisted' => true]);
    $session = createStudentQuizSession($student);
    $question = $session->questions()->first();

    $this->actingAs($student)
        ->get(route('student.results', $session))
        ->assertStatus(409);

    $this->postJson(route('student.quiz.submit', $session), [
        'answers' => [$question->id => 'Wrong'],
    ])->assertOk();

    $this->get(route('student.results', $session))
        ->assertOk()
        ->assertSee('Question 1')
        ->assertSee('Your answer:')
        ->assertSee('Wrong')
        ->assertSee('Correct answer:')
        ->assertSee('Correct')
        ->assertSee('Secret explanation');
});

test('a student cannot view another students quiz results', function () {
    $owner = User::factory()->create(['is_whitelisted' => true]);
    $otherStudent = User::factory()->create(['is_whitelisted' => true]);
    $session = createStudentQuizSession($owner);
    $session->update(['status' => 'completed', 'answers' => []]);

    $this->actingAs($otherStudent)
        ->get(route('student.results', $session))
        ->assertForbidden();
});

test('a student cannot submit a score for another students quiz session', function () {
    $owner = User::factory()->create(['is_whitelisted' => true]);
    $otherStudent = User::factory()->create(['is_whitelisted' => true]);
    $session = createStudentQuizSession($owner);

    $this->actingAs($otherStudent)
        ->postJson(route('student.quiz.submit', $session), ['answers' => []])
        ->assertForbidden();

    expect($session->fresh()->score)->toBe(0)
        ->and($session->fresh()->status)->toBe('active');
});

test('quiz answer keys are not rendered to the student', function () {
    $student = User::factory()->create(['is_whitelisted' => true]);
    $session = createStudentQuizSession($student);

    $this->actingAs($student)
        ->get(route('student.play', $session))
        ->assertOk()
        ->assertDontSee('data-correct')
        ->assertDontSee('Secret explanation');
});

test('fill-in-the-blank student answers are graded case-insensitively and reviewed after submission', function () {
    $student = User::factory()->create(['is_whitelisted' => true]);
    $session = createStudentQuizSession($student);
    $question = $session->questions()->first();
    $question->update([
        'question_text' => 'The PHP array loop is _____.',
        'question_type' => 'fill_in_the_blank',
        'options' => [],
        'correct_answer' => 'foreach',
        'accepted_answers' => ['foreach', 'for each'],
        'explanation' => 'foreach iterates over each item.',
    ]);

    $this->actingAs($student)
        ->get(route('student.play', $session))
        ->assertOk()
        ->assertSee('Type your answer')
        ->assertDontSee('foreach')
        ->assertDontSee('foreach iterates');

    $this->postJson(route('student.quiz.answer', $session), [
        'question_id' => $question->id,
        'answer' => ' FOREACH ',
        'timed_out' => false,
    ])->assertOk()->assertJsonPath('is_correct', true)->assertJsonMissingPath('correct_answer');

    $this->postJson(route('student.quiz.submit', $session), [
        'answers' => [$question->id => 'FOREACH'],
    ])->assertOk()->assertJsonPath('score', 1);

    $this->get(route('student.results', $session))
        ->assertOk()
        ->assertSee('Correct')
        ->assertSee('FOREACH');
});

test('blank fill-in-the-blank answers are saved as unanswered and shown in review', function () {
    $student = User::factory()->create(['is_whitelisted' => true]);
    $session = createStudentQuizSession($student);
    $question = $session->questions()->first();
    $question->update([
        'question_type' => 'fill_in_the_blank',
        'options' => [],
        'correct_answer' => 'foreach',
        'accepted_answers' => ['foreach'],
        'explanation' => '',
    ]);

    $this->actingAs($student)
        ->postJson(route('student.quiz.submit', $session), [
            'answers' => [$question->id => ''],
        ])
        ->assertOk()
        ->assertJsonPath('score', 0);

    $this->get(route('student.results', $session))
        ->assertOk()
        ->assertSee('No answer submitted')
        ->assertSee('No explanation provided.');
});

test('student document generation stores its PDF and creates only its own formatted session', function () {
    Storage::fake('local');
    $student = User::factory()->create(['is_whitelisted' => true]);
    $generatedQuestion = [
        'question_text' => 'The PHP array loop is _____.',
        'options' => [],
        'question_type' => 'fill_in_the_blank',
        'correct_answer' => 'foreach',
        'accepted_answers' => ['foreach'],
        'explanation' => 'foreach iterates over each item.',
        'needs_review' => false,
    ];

    $this->mock(GeminiQuizService::class, function ($mock) {
        $mock->shouldReceive('extractPdfText')->once()->andReturn('Extracted course text.');
    });
    $this->mock(QuizGeneratorService::class, function ($mock) use ($generatedQuestion) {
        $mock->shouldReceive('generateDocumentQuizFromText')
            ->once()
            ->with('Extracted course text.', null, 'fill_in_the_blank', Mockery::type('string'))
            ->andReturn([$generatedQuestion]);
    });

    $response = $this->actingAs($student)
        ->withHeader('Accept', 'application/json')
        ->post(route('student.quiz.generate.document'), [
            'pdf' => UploadedFile::fake()->createWithContent('course.pdf', "%PDF-1.4\n%%EOF"),
            'title' => 'PHP loops review',
            'language' => 'PHP',
            'count' => '0',
            'quiz_format' => 'fill_in_the_blank',
            'seconds_per_question' => '105',
        ]);

    $response->assertOk()
        ->assertJsonPath('question_count', 1)
        ->assertJsonPath('seconds_per_question', 100)
        ->assertJsonPath('success', true);

    $session = QuizSession::query()->firstOrFail();
    $upload = StoredUpload::query()->firstOrFail();
    expect($session->student_id)->toBe($student->id)
        ->and($session->source_file_id)->toBe($upload->id)
        ->and($session->seconds_per_question)->toBe(100)
        ->and($session->quiz_format)->toBe('fill_in_the_blank')
        ->and($upload->student_id)->toBe($student->id)
        ->and($upload->professor_id)->toBeNull();

    Storage::disk('local')->assertExists($upload->file_path);
});

test('student document generation accepts a 150 question request without padding provider results', function () {
    Storage::fake('local');
    $student = User::factory()->create(['is_whitelisted' => true]);
    $generatedQuestion = [
        'question_text' => 'The PHP array loop is _____.',
        'options' => [],
        'question_type' => 'fill_in_the_blank',
        'correct_answer' => 'foreach',
        'accepted_answers' => ['foreach'],
        'explanation' => 'foreach iterates over each item.',
        'needs_review' => false,
    ];

    $this->mock(GeminiQuizService::class, function ($mock) {
        $mock->shouldReceive('extractPdfText')->once()->andReturn('Extracted course text.');
    });
    $this->mock(QuizGeneratorService::class, function ($mock) use ($generatedQuestion) {
        $mock->shouldReceive('generateDocumentQuizFromText')
            ->once()
            ->with('Extracted course text.', 150, 'fill_in_the_blank', Mockery::type('string'))
            ->andReturn([$generatedQuestion]);
    });

    $this->actingAs($student)
        ->withHeader('Accept', 'application/json')
        ->post(route('student.quiz.generate.document'), [
            'pdf' => UploadedFile::fake()->createWithContent('course.pdf', "%PDF-1.4\n%%EOF"),
            'title' => 'PHP loops review',
            'language' => 'PHP',
            'count' => '150',
            'quiz_format' => 'fill_in_the_blank',
        ])
        ->assertOk()
        ->assertJsonPath('question_count', 1);

    expect(QuizSession::query()->firstOrFail()->questions()->count())->toBe(1);
});
