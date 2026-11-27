<?php

use App\Models\Quiz;
use App\Models\QuizSession;
use App\Models\SessionParticipant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createMonitoringSession(User $professor, array $status = ['status' => 'in_progress']): array
{
    $quiz = Quiz::create([
        'professor_id' => $professor->id,
        'title' => 'Monitoring quiz',
        'question_count' => 3,
        'status' => 'ready',
    ]);

    $questions = collect([
        ['question_text' => 'Question one', 'options' => ['A', 'B'], 'correct_answer' => 'A', 'explanation' => 'Explanation one'],
        ['question_text' => 'Question two', 'options' => ['A', 'B'], 'correct_answer' => 'B', 'explanation' => 'Explanation two'],
        ['question_text' => 'Question three', 'options' => ['A', 'B'], 'correct_answer' => 'A', 'explanation' => 'Explanation three'],
    ])->map(fn (array $question) => $quiz->questions()->create($question));

    $session = QuizSession::create(array_merge([
        'quiz_id' => $quiz->id,
        'professor_id' => $professor->id,
        'type' => 'live',
        'quiz_code' => 'QZ-MONITOR',
        'started_at' => now(),
    ], $status));

    return [$session, $questions];
}

test('live summary and roster use saved responses, hide pre-submit scores, and omit emails', function () {
    $professor = User::factory()->create(['role' => 'professor']);
    $waiting = User::factory()->create(['name' => 'Ada Waiting', 'email' => 'private@example.test']);
    $active = User::factory()->create(['name' => 'Zed Active', 'email' => 'active@example.test']);
    [$session, $questions] = createMonitoringSession($professor);

    SessionParticipant::create([
        'quiz_session_id' => $session->id,
        'student_id' => $waiting->id,
        'status' => 'joined',
        'joined_at' => now()->subMinute(),
    ]);
    SessionParticipant::create([
        'quiz_session_id' => $session->id,
        'student_id' => $active->id,
        'status' => 'in_progress',
        'joined_at' => now()->subMinute(),
        'started_at' => now()->subSeconds(30),
        'last_active_at' => now(),
        'answers' => [
            (string) $questions[0]->id => 'A',
            (string) $questions[1]->id => 'A',
            (string) $questions[2]->id => '',
        ],
    ]);

    $this->actingAs($professor)
        ->getJson(route('professor.sessions.summary', $session))
        ->assertOk()
        ->assertJsonPath('analytics.summary.participant_count', 2)
        ->assertJsonPath('analytics.summary.completed_count', 0)
        ->assertJsonPath('analytics.summary.answered_count', 2)
        ->assertJsonPath('analytics.summary.correct_count', 1)
        ->assertJsonPath('analytics.summary.accuracy_percent', 50)
        ->assertJsonPath('analytics.summary.questions_needing_review', 1)
        ->assertJsonPath('analytics.questions.2.response_count', 0)
        ->assertJsonPath('analytics.questions.2.unanswered_count', 2);

    $this->getJson(route('professor.sessions.participants', [
        'session' => $session,
        'status' => 'waiting',
        'search' => 'Ada',
    ]))
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.name', 'Ada Waiting')
        ->assertJsonPath('data.0.status', 'Waiting')
        ->assertJsonPath('data.0.started', false)
        ->assertJsonPath('data.0.score', null)
        ->assertJsonMissingPath('data.0.email')
        ->assertJsonMissingPath('data.0.answers');

    $this->getJson(route('professor.sessions.participants', [
        'session' => $session,
        'status' => 'in_progress',
    ]))
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Zed Active')
        ->assertJsonPath('data.0.answered_count', 2)
        ->assertJsonPath('data.0.question_count', 3)
        ->assertJsonPath('data.0.correct_count', 1)
        ->assertJsonPath('data.0.accuracy_percent', 50)
        ->assertJsonPath('data.0.score', null)
        ->assertJsonStructure(['data' => [[
            'answered_count',
            'question_count',
            'correct_count',
            'accuracy_percent',
            'status',
            'last_activity_at',
            'updated_at',
        ]]]);
});

test('participant details show answer states without answer contents and session ownership is enforced', function () {
    $professor = User::factory()->create(['role' => 'professor']);
    $otherProfessor = User::factory()->create(['role' => 'professor']);
    $student = User::factory()->create(['name' => 'Progress Student']);
    [$session, $questions] = createMonitoringSession($professor);
    $participant = SessionParticipant::create([
        'quiz_session_id' => $session->id,
        'student_id' => $student->id,
        'status' => 'in_progress',
        'started_at' => now(),
        'last_active_at' => now(),
        'answers' => [
            (string) $questions[0]->id => 'A',
            (string) $questions[1]->id => 'A',
        ],
    ]);

    $this->actingAs($professor)
        ->getJson(route('professor.sessions.participants.show', [$session, $participant]))
        ->assertOk()
        ->assertJsonPath('participant.name', 'Progress Student')
        ->assertJsonPath('questions.0.status', 'Correct')
        ->assertJsonPath('questions.1.status', 'Incorrect')
        ->assertJsonPath('questions.2.status', 'Unanswered')
        ->assertJsonMissing(['answer' => 'A']);

    $this->actingAs($otherProfessor)
        ->getJson(route('professor.sessions.summary', $session))
        ->assertForbidden();

    $this->getJson(route('professor.sessions.participants.show', [$session, $participant]))
        ->assertForbidden();
});

test('stale and ended participants are classified as disconnected while late joins remain waiting', function () {
    $professor = User::factory()->create(['role' => 'professor']);
    $lateJoin = User::factory()->create(['name' => 'Late Join']);
    $staleStudent = User::factory()->create(['name' => 'Stale Student']);
    [$session] = createMonitoringSession($professor);
    SessionParticipant::create([
        'quiz_session_id' => $session->id,
        'student_id' => $lateJoin->id,
        'status' => 'joined',
        'joined_at' => now(),
    ]);
    $staleParticipant = SessionParticipant::create([
        'quiz_session_id' => $session->id,
        'student_id' => $staleStudent->id,
        'status' => 'in_progress',
        'joined_at' => now()->subMinutes(5),
        'started_at' => now()->subMinutes(4),
        'last_active_at' => now()->subMinutes(3),
    ]);

    $this->actingAs($professor)
        ->getJson(route('professor.sessions.participants', $session))
        ->assertOk()
        ->assertJsonPath('data.0.status', 'Waiting')
        ->assertJsonPath('data.1.status', 'Disconnected');

    $this->getJson(route('professor.sessions.participants', [
        'session' => $session,
        'status' => 'disconnected',
    ]))
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.id', $staleParticipant->id);

    $session->update(['status' => 'completed']);
    $this->getJson(route('professor.sessions.participants', [
        'session' => $session,
        'status' => 'disconnected',
    ]))
        ->assertOk()
        ->assertJsonPath('meta.total', 2);
});

test('participant roster pagination retains the requested page size and page', function () {
    $professor = User::factory()->create(['role' => 'professor']);
    [$session] = createMonitoringSession($professor);

    foreach (range(1, 12) as $index) {
        $student = User::factory()->create(['name' => sprintf('Student %02d', $index)]);
        SessionParticipant::create([
            'quiz_session_id' => $session->id,
            'student_id' => $student->id,
            'status' => 'joined',
            'joined_at' => now(),
        ]);
    }

    $this->actingAs($professor)
        ->getJson(route('professor.sessions.participants', [
            'session' => $session,
            'per_page' => 10,
            'page' => 2,
        ]))
        ->assertOk()
        ->assertJsonPath('meta.current_page', 2)
        ->assertJsonPath('meta.last_page', 2)
        ->assertJsonPath('meta.total', 12)
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.name', 'Student 11');
});

test('late joiners stay waiting until opening the quiz and reconnecting refreshes activity', function () {
    $professor = User::factory()->create(['role' => 'professor']);
    $student = User::factory()->create(['is_whitelisted' => true]);
    [$session] = createMonitoringSession($professor);

    $this->actingAs($student)
        ->post(route('student.live.join.store'), ['quiz_code' => $session->quiz_code])
        ->assertRedirect(route('student.live.lobby', $session));

    $participant = SessionParticipant::where('quiz_session_id', $session->id)
        ->where('student_id', $student->id)
        ->firstOrFail();
    expect($participant->started_at)->toBeNull();

    $this->getJson(route('student.live.status', $session))
        ->assertOk()
        ->assertJsonMissingPath('participants.0.score');

    $participant->forceFill([
        'status' => 'in_progress',
        'started_at' => now()->subMinutes(4),
        'last_active_at' => now()->subMinutes(3),
    ])->save();
    $oldActivity = $participant->last_active_at->copy();

    $this->get(route('student.live.take', $session))
        ->assertOk();

    expect($participant->fresh()->started_at)->not->toBeNull()
        ->and($participant->fresh()->last_active_at->gt($oldActivity))->toBeTrue();

    $this->actingAs($professor)
        ->getJson(route('professor.sessions.participants', $session))
        ->assertOk()
        ->assertJsonPath('data.0.status', 'In progress');
});

test('live submission records timestamps and duplicate submissions return the stored score', function () {
    $professor = User::factory()->create(['role' => 'professor']);
    $student = User::factory()->create(['is_whitelisted' => true]);
    [$session, $questions] = createMonitoringSession($professor);
    $participant = SessionParticipant::create([
        'quiz_session_id' => $session->id,
        'student_id' => $student->id,
        'status' => 'joined',
        'joined_at' => now()->subMinute(),
    ]);
    $answers = [
        $questions[0]->id => 'A',
        $questions[1]->id => 'A',
        $questions[2]->id => '',
    ];

    $this->actingAs($student)
        ->postJson(route('student.live.submit', $session), ['answers' => $answers])
        ->assertOk()
        ->assertJsonPath('score', 1)
        ->assertJsonPath('total', 3)
        ->assertJsonPath('already_submitted', false);

    expect($participant->fresh()->started_at)->not->toBeNull()
        ->and($participant->fresh()->last_active_at)->not->toBeNull()
        ->and($participant->fresh()->submitted_at)->not->toBeNull();

    $this->postJson(route('student.live.submit', $session), ['answers' => []])
        ->assertOk()
        ->assertJsonPath('score', 1)
        ->assertJsonPath('already_submitted', true);

    $this->actingAs($professor)
        ->getJson(route('professor.sessions.participants', $session))
        ->assertOk()
        ->assertJsonPath('data.0.status', 'Submitted')
        ->assertJsonPath('data.0.score', 1)
        ->assertJsonPath('data.0.score_label', '1 / 3');
});

test('session export is owner-only, available after ending, and handles empty sessions', function () {
    $professor = User::factory()->create(['role' => 'professor']);
    $otherProfessor = User::factory()->create(['role' => 'professor']);
    [$session, $questions] = createMonitoringSession($professor, ['status' => 'completed']);
    $student = User::factory()->create(['name' => 'Export Student', 'email' => 'private-export@example.test']);
    SessionParticipant::create([
        'quiz_session_id' => $session->id,
        'student_id' => $student->id,
        'status' => 'completed',
        'joined_at' => now()->subMinutes(2),
        'started_at' => now()->subMinute(),
        'last_active_at' => now(),
        'submitted_at' => now(),
        'answers' => [(string) $questions[0]->id => 'SECRET_ANSWER'],
    ]);

    $export = $this->actingAs($professor)
        ->get(route('professor.sessions.export', $session))
        ->assertOk()
        ->assertDownload('live-session-'.$session->id.'-summary.csv');
    expect($export->streamedContent())
        ->toContain('Export Student')
        ->not->toContain('private-export@example.test')
        ->not->toContain('SECRET_ANSWER');

    $this->actingAs($otherProfessor)
        ->get(route('professor.sessions.export', $session))
        ->assertForbidden();

    $activeSession = QuizSession::create([
        'quiz_id' => $session->quiz_id,
        'professor_id' => $professor->id,
        'status' => 'in_progress',
        'type' => 'live',
        'quiz_code' => 'QZ-EMPTY',
    ]);
    $this->actingAs($professor)
        ->getJson(route('professor.sessions.summary', $activeSession))
        ->assertOk()
        ->assertJsonPath('analytics.summary.participant_count', 0)
        ->assertJsonPath('analytics.summary.completion_rate_percent', 0)
        ->assertJsonPath('analytics.summary.accuracy_percent', 0);

    $this->get(route('professor.sessions.export', $activeSession))->assertStatus(409);
});
