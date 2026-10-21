<?php

use App\Models\Quiz;
use App\Models\QuizSession;
use App\Models\SessionParticipant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('bulk whitelist accepts email lists without single-entry fields', function () {
    $professor = User::factory()->create(['role' => 'professor']);

    $this->actingAs($professor)
        ->post(route('professor.whitelist.store'), [
            'bulk_emails' => "alice@example.test\nbob@example.test",
        ])
        ->assertRedirect();

    expect(\App\Models\WhitelistedStudent::whereIn('email', [
        'alice@example.test',
        'bob@example.test',
    ])->count())->toBe(2);
});

test('single whitelist entries continue to be accepted', function () {
    $professor = User::factory()->create(['role' => 'professor']);

    $this->actingAs($professor)
        ->post(route('professor.whitelist.store'), [
            'name' => 'Alice Example',
            'email' => 'alice@example.test',
        ])
        ->assertRedirect();

    expect(\App\Models\WhitelistedStudent::where('email', 'alice@example.test')->exists())->toBeTrue();
});

test('professor login redirects to the professor dashboard', function () {
    $professor = User::factory()->create([
        'role' => 'professor',
        'password' => bcrypt('password'),
    ]);

    $this->post(route('login'), [
        'email' => $professor->email,
        'password' => 'password',
    ])->assertRedirect(route('professor.dashboard'));
});

test('whitelisted student login redirects to the student dashboard', function () {
    $student = User::factory()->create([
        'role' => 'student',
        'is_whitelisted' => true,
        'password' => bcrypt('password'),
    ]);

    $this->post(route('login'), [
        'email' => $student->email,
        'password' => 'password',
    ])->assertRedirect(route('student.dashboard'));
});

test('live quiz submission requires an active participant', function () {
    $professor = User::factory()->create(['role' => 'professor']);
    $student = User::factory()->create(['is_whitelisted' => true]);
    $quiz = Quiz::create([
        'professor_id' => $professor->id,
        'title' => 'Live Quiz',
        'question_count' => 1,
        'status' => 'ready',
    ]);
    $session = QuizSession::create([
        'quiz_id' => $quiz->id,
        'professor_id' => $professor->id,
        'status' => 'in_progress',
        'type' => 'live',
        'quiz_code' => 'QZ-TEST',
    ]);
    $question = $quiz->questions()->create([
        'question_text' => 'Pick the correct answer.',
        'options' => ['Correct', 'Wrong'],
        'correct_answer' => 'Correct',
        'explanation' => 'Secret live explanation',
    ]);

    $this->actingAs($student)
        ->postJson(route('student.live.submit', $session), [
            'answers' => [$question->id => 'Correct'],
        ])
        ->assertForbidden();
});

test('live quiz score is calculated from stored answers for an active participant', function () {
    $professor = User::factory()->create(['role' => 'professor']);
    $student = User::factory()->create(['is_whitelisted' => true]);
    $quiz = Quiz::create([
        'professor_id' => $professor->id,
        'title' => 'Live Quiz',
        'question_count' => 1,
        'status' => 'ready',
    ]);
    $session = QuizSession::create([
        'quiz_id' => $quiz->id,
        'professor_id' => $professor->id,
        'status' => 'in_progress',
        'type' => 'live',
        'quiz_code' => 'QZ-LIVE',
    ]);
    $question = $quiz->questions()->create([
        'question_text' => 'Pick the correct answer.',
        'options' => ['Correct', 'Wrong'],
        'correct_answer' => 'Correct',
        'explanation' => 'Secret live explanation',
    ]);
    $participant = SessionParticipant::create([
        'quiz_session_id' => $session->id,
        'student_id' => $student->id,
        'status' => 'joined',
    ]);

    $this->actingAs($student)
        ->postJson(route('student.live.submit', $session), [
            'answers' => [$question->id => 'Wrong'],
            'score' => 1,
        ])
        ->assertOk()
        ->assertJsonPath('score', 0);

    expect($participant->fresh()->score)->toBe(0)
        ->and($participant->fresh()->status)->toBe('completed');

    $this->get(route('student.live.complete', $session))
        ->assertOk()
        ->assertSee('Question 1')
        ->assertSee('Your answer:')
        ->assertSee('Wrong')
        ->assertSee('Correct answer:')
        ->assertSee('Correct')
        ->assertSee('Secret live explanation');
});

test('live answer matching ignores generated leading and trailing whitespace', function () {
    $professor = User::factory()->create(['role' => 'professor']);
    $student = User::factory()->create(['is_whitelisted' => true]);
    $quiz = Quiz::create([
        'professor_id' => $professor->id,
        'title' => 'Live Quiz',
        'question_count' => 1,
        'status' => 'ready',
    ]);
    $session = QuizSession::create([
        'quiz_id' => $quiz->id,
        'professor_id' => $professor->id,
        'status' => 'in_progress',
        'type' => 'live',
        'quiz_code' => 'QZ-SPACE',
    ]);
    $question = $quiz->questions()->create([
        'question_text' => 'Pick the correct answer.',
        'options' => [' Correct ', ' Wrong '],
        'correct_answer' => ' Correct ',
        'explanation' => 'Explanation',
    ]);
    $participant = SessionParticipant::create([
        'quiz_session_id' => $session->id,
        'student_id' => $student->id,
        'status' => 'joined',
    ]);

    $this->actingAs($student)
        ->postJson(route('student.live.submit', $session), [
            'answers' => [$question->id => 'Correct'],
        ])
        ->assertOk()
        ->assertJsonPath('score', 1);

    expect($participant->fresh()->answers[$question->id])->toBe('Correct');
});

test('live quiz answer keys are not rendered to students', function () {
    $professor = User::factory()->create(['role' => 'professor']);
    $student = User::factory()->create(['is_whitelisted' => true]);
    $quiz = Quiz::create([
        'professor_id' => $professor->id,
        'title' => 'Live Quiz',
        'question_count' => 1,
        'status' => 'ready',
    ]);
    $session = QuizSession::create([
        'quiz_id' => $quiz->id,
        'professor_id' => $professor->id,
        'status' => 'in_progress',
        'type' => 'live',
        'quiz_code' => 'QZ-VIEW',
    ]);
    $quiz->questions()->create([
        'question_text' => 'Pick the correct answer.',
        'options' => ['Correct', 'Wrong'],
        'correct_answer' => 'Correct',
        'explanation' => 'Secret live explanation',
    ]);
    SessionParticipant::create([
        'quiz_session_id' => $session->id,
        'student_id' => $student->id,
        'status' => 'joined',
    ]);

    $this->actingAs($student)
        ->get(route('student.live.take', $session))
        ->assertOk()
        ->assertDontSee('data-correct')
        ->assertDontSee('Secret live explanation');
});
