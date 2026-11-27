<?php

use App\Models\Quiz;
use App\Models\QuizSession;
use App\Models\SessionParticipant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createAnalyticsQuiz(User $professor, int $questionCount = 2): array
{
    $quiz = Quiz::create([
        'professor_id' => $professor->id,
        'title' => 'Analytics quiz',
        'question_count' => $questionCount,
        'status' => 'ready',
    ]);

    $questions = collect();
    for ($index = 0; $index < $questionCount; $index++) {
        $questions->push($quiz->questions()->create([
            'question_text' => 'Question '.($index + 1),
            'options' => ['A', 'B'],
            'correct_answer' => $index === 0 ? 'A' : 'B',
            'explanation' => 'Explanation '.($index + 1),
        ]));
    }

    $session = QuizSession::create([
        'quiz_id' => $quiz->id,
        'professor_id' => $professor->id,
        'status' => 'in_progress',
        'type' => 'live',
        'quiz_code' => 'QZ-ANALYTICS',
    ]);

    return [$quiz, $session, $questions];
}

test('professor live poll returns name-sorted student analytics and unassigned topic and difficulty groups', function () {
    $professor = User::factory()->create(['role' => 'professor']);
    $slowStudent = User::factory()->create(['name' => 'Ada Slow', 'is_whitelisted' => true]);
    $fastStudent = User::factory()->create(['name' => 'Zed Fast', 'is_whitelisted' => true]);
    [, $session, $questions] = createAnalyticsQuiz($professor);

    SessionParticipant::create([
        'quiz_session_id' => $session->id,
        'student_id' => $slowStudent->id,
        'status' => 'in_progress',
        'answers' => [(string) $questions[0]->id => 'A'],
    ]);
    SessionParticipant::create([
        'quiz_session_id' => $session->id,
        'student_id' => $fastStudent->id,
        'status' => 'in_progress',
        'answers' => [
            (string) $questions[0]->id => 'B',
            (string) $questions[1]->id => 'B',
        ],
    ]);

    $this->actingAs($professor)
        ->getJson(route('professor.sessions.status', $session))
        ->assertOk()
        ->assertJsonPath('analytics.question_count', 2)
        ->assertJsonPath('analytics.participant_count', 2)
        ->assertJsonPath('analytics.students.0.label', 'Ada Slow')
        ->assertJsonPath('analytics.students.0.answered_count', 1)
        ->assertJsonPath('analytics.students.0.remaining_count', 1)
        ->assertJsonPath('analytics.students.1.label', 'Zed Fast')
        ->assertJsonPath('analytics.topics.0.label', 'Unassigned')
        ->assertJsonPath('analytics.topics.0.total_count', 2)
        ->assertJsonPath('analytics.topics.0.average_answered_count', 1.5)
        ->assertJsonPath('analytics.difficulties.0.label', 'Unassigned')
        ->assertJsonPath('analytics.difficulties.0.average_correct_count', 1);

    $this->get(route('professor.sessions.lobby', $session))
        ->assertOk()
        ->assertSee('Live class analytics')
        ->assertSee('By difficulty')
        ->assertSee('Question breakdown');
});

test('live answer progress is ownership-checked and immediately appears in professor analytics', function () {
    $professor = User::factory()->create(['role' => 'professor']);
    $student = User::factory()->create(['is_whitelisted' => true]);
    $otherStudent = User::factory()->create(['is_whitelisted' => true]);
    [, $session, $questions] = createAnalyticsQuiz($professor);
    $participant = SessionParticipant::create([
        'quiz_session_id' => $session->id,
        'student_id' => $student->id,
        'status' => 'joined',
    ]);

    $this->actingAs($student)
        ->postJson(route('student.live.progress', $session), [
            'question_id' => $questions[0]->id,
            'answer' => 'A',
        ])
        ->assertOk()
        ->assertJsonPath('saved', true);

    expect($participant->fresh()->status)->toBe('in_progress')
        ->and($participant->fresh()->answers)->toBe([(string) $questions[0]->id => 'A']);

    $this->actingAs($otherStudent)
        ->postJson(route('student.live.progress', $session), [
            'question_id' => $questions[0]->id,
            'answer' => 'A',
        ])
        ->assertForbidden();

    $this->actingAs($professor)
        ->getJson(route('professor.sessions.status', $session))
        ->assertOk()
        ->assertJsonPath('analytics.students.0.answered_count', 1)
        ->assertJsonPath('analytics.students.0.remaining_count', 1)
        ->assertJsonPath('analytics.students.0.correct_count', 1);
});
