<?php

use App\Models\Quiz;
use App\Models\QuizSession;
use App\Models\SessionParticipant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('active live quiz tab-switch flags are recorded and exposed to the professor poll', function () {
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
        'quiz_code' => 'QZ-FLAG',
    ]);
    $participant = SessionParticipant::create([
        'quiz_session_id' => $session->id,
        'student_id' => $student->id,
        'status' => 'joined',
    ]);

    $this->actingAs($student)
        ->postJson(route('student.live.flag-tab-switch', $session))
        ->assertOk()
        ->assertJsonPath('flagged', true)
        ->assertJsonPath('tab_switch_count', 1)
        ->assertJsonPath('reason', 'Tab switch / window blur detected');

    expect($participant->fresh()->tab_switch_count)->toBe(1)
        ->and($participant->fresh()->last_tab_switch_at)->not->toBeNull();

    $this->actingAs($professor)
        ->getJson(route('professor.sessions.status', $session))
        ->assertOk()
        ->assertJsonPath('participants.0.tab_switch_count', 1)
        ->assertJsonPath('participants.0.last_tab_switch_reason', 'Tab switch / window blur detected')
        ->assertJsonPath('participants.0.last_tab_switch_at', $participant->fresh()->last_tab_switch_at->toIso8601String());
});

test('students cannot flag another participant or a session that is not in progress', function () {
    $professor = User::factory()->create(['role' => 'professor']);
    $student = User::factory()->create(['is_whitelisted' => true]);
    $otherStudent = User::factory()->create(['is_whitelisted' => true]);
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
        'quiz_code' => 'QZ-OWNER',
    ]);
    $participant = SessionParticipant::create([
        'quiz_session_id' => $session->id,
        'student_id' => $otherStudent->id,
        'status' => 'joined',
    ]);

    $this->actingAs($student)
        ->postJson(route('student.live.flag-tab-switch', $session))
        ->assertForbidden();

    expect($participant->fresh()->tab_switch_count)->toBe(0);

    $session->update(['status' => 'completed']);

    $this->actingAs($otherStudent)
        ->postJson(route('student.live.flag-tab-switch', $session))
        ->assertStatus(409);

    expect($participant->fresh()->tab_switch_count)->toBe(0);
});
