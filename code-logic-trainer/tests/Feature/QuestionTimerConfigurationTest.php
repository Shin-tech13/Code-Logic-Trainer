<?php

use App\Models\Quiz;
use App\Models\QuizSession;
use App\Models\SessionParticipant;
use App\Models\User;
use App\Services\GeminiQuizService;
use App\Services\QuizGeneratorService;
use App\Support\QuestionTimer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('question timer normalization defaults empty and non-positive values and clamps high values', function () {
    expect(QuestionTimer::normalize(null))->toBe(20)
        ->and(QuestionTimer::normalize(''))->toBe(20)
        ->and(QuestionTimer::normalize('0'))->toBe(20)
        ->and(QuestionTimer::normalize('-4'))->toBe(20)
        ->and(QuestionTimer::normalize('1'))->toBe(1)
        ->and(QuestionTimer::normalize('100'))->toBe(100)
        ->and(QuestionTimer::normalize('105'))->toBe(100)
        ->and(QuestionTimer::normalize('999'))->toBe(100);
});

test('timer database defaults are twenty seconds', function () {
    $professor = User::factory()->create(['role' => 'professor']);
    $student = User::factory()->create(['is_whitelisted' => true]);
    $quiz = Quiz::create([
        'professor_id' => $professor->id,
        'title' => 'Default timer quiz',
        'question_count' => 1,
        'status' => 'ready',
    ]);
    $session = QuizSession::create([
        'student_id' => $student->id,
        'language' => 'PHP',
        'topic' => 'Arrays',
    ]);

    expect((int) DB::table('quizzes')->where('id', $quiz->id)->value('seconds_per_question'))->toBe(20)
        ->and((int) DB::table('quiz_sessions')->where('id', $session->id)->value('seconds_per_question'))->toBe(20);
});

test('student topic quiz saves the normalized timer and renders that countdown', function () {
    $student = User::factory()->create(['is_whitelisted' => true]);
    $generatedQuestion = [
        'question_text' => 'Which value is true?',
        'options' => ['A', 'B'],
        'correct_answer' => 'A',
        'explanation' => 'A is true.',
    ];

    $this->mock(GeminiQuizService::class);
    $this->mock(QuizGeneratorService::class, function ($mock) use ($generatedQuestion) {
        $mock->shouldReceive('generateStudentQuiz')
            ->once()
            ->with('PHP', 'Arrays', 10)
            ->andReturn([$generatedQuestion]);
    });

    $response = $this->actingAs($student)
        ->postJson(route('student.quiz.generate'), [
            'language' => 'PHP',
            'topic' => 'Arrays',
            'seconds_per_question' => '105',
        ])
        ->assertOk()
        ->assertJsonPath('seconds_per_question', 100);

    $session = QuizSession::query()->firstOrFail();
    expect($session->seconds_per_question)->toBe(100);

    $this->get(route('student.play', $session))
        ->assertOk()
        ->assertSee('01:40');
});

test('professor quiz and waiting live-session timer settings normalize before saving', function () {
    $professor = User::factory()->create(['role' => 'professor']);
    $quiz = Quiz::create([
        'professor_id' => $professor->id,
        'title' => 'Timed quiz',
        'question_count' => 1,
        'status' => 'ready',
    ]);
    $quiz->questions()->create([
        'question_text' => 'Which value is true?',
        'options' => ['A', 'B'],
        'correct_answer' => 'A',
        'explanation' => 'A is true.',
    ]);

    $this->actingAs($professor)
        ->patch(route('professor.quizzes.settings.update', $quiz), [
            'seconds_per_question' => '105',
        ])
        ->assertRedirect();

    expect($quiz->fresh()->seconds_per_question)->toBe(100);

    $this->post(route('professor.sessions.launch', $quiz))
        ->assertRedirect();

    $session = QuizSession::query()->where('quiz_id', $quiz->id)->firstOrFail();
    expect($session->seconds_per_question)->toBe(100);

    $this->patch(route('professor.sessions.timer.update', $session), [
        'seconds_per_question' => '-5',
    ])->assertRedirect();

    expect($session->fresh()->seconds_per_question)->toBe(20);

    $session->update(['status' => 'in_progress']);
    $this->patch(route('professor.sessions.timer.update', $session), [
        'seconds_per_question' => '30',
    ])->assertStatus(409);

    expect($session->fresh()->seconds_per_question)->toBe(20);

    $student = User::factory()->create(['is_whitelisted' => true]);
    SessionParticipant::create([
        'quiz_session_id' => $session->id,
        'student_id' => $student->id,
        'status' => 'joined',
    ]);

    $this->actingAs($student)
        ->get(route('student.live.take', $session))
        ->assertOk()
        ->assertSee('00:20');
});
