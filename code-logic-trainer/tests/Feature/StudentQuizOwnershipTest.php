<?php

use App\Models\QuizSession;
use App\Models\Question;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

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
        ->assertOk();
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
