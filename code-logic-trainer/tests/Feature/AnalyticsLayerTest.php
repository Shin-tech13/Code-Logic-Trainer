<?php

use App\Models\AnalyticsEvent;
use App\Models\Classroom;
use App\Models\CodeChallenge;
use App\Models\Quiz;
use App\Models\QuizSession;
use App\Models\SessionParticipant;
use App\Models\User;
use App\Services\AnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function makeAnalyticsLayerReport(User $professor, int $cohortSize): array
{
    $classroom = Classroom::create([
        'professor_id' => $professor->id,
        'title' => 'Analytics cohort',
        'class_code' => 'AN-'.str_pad((string) $cohortSize, 4, '0', STR_PAD_LEFT),
    ]);
    $students = User::factory()->count($cohortSize)->create(['role' => 'student']);
    $classroom->students()->attach($students->modelKeys());
    $quiz = Quiz::create([
        'professor_id' => $professor->id,
        'title' => 'Cohort assessment',
        'question_count' => 1,
        'status' => 'ready',
    ]);
    $question = $quiz->questions()->create([
        'question_text' => 'Which option is correct?',
        'options' => ['A', 'B'],
        'correct_answer' => 'A',
        'explanation' => 'Option A is correct.',
    ]);
    $session = QuizSession::create([
        'quiz_id' => $quiz->id,
        'professor_id' => $professor->id,
        'status' => 'completed',
        'type' => 'live',
        'quiz_code' => 'QZ-'.str_pad((string) $cohortSize, 4, '0', STR_PAD_LEFT),
    ]);

    foreach ($students as $index => $student) {
        $answer = $index < 3 ? 'A' : 'B';
        SessionParticipant::create([
            'quiz_session_id' => $session->id,
            'student_id' => $student->id,
            'status' => 'completed',
            'score' => $answer === 'A' ? 1 : 0,
            'joined_at' => now()->subMinutes($index),
            'answers' => [(string) $question->id => $answer],
        ]);
        $selfStudy = QuizSession::create([
            'student_id' => $student->id,
            'language' => 'PHP',
            'topic' => 'Loops',
            'score' => $index < 3 ? 1 : 0,
            'status' => 'completed',
            'type' => 'self_study',
        ]);
        $selfStudy->questions()->create([
            'question_text' => 'Loop diagnostic',
            'options' => ['A', 'B'],
            'correct_answer' => 'A',
            'explanation' => 'Loop explanation.',
        ]);
    }

    return [$quiz, $question, $students, $classroom];
}

it('masks question breakdowns below five and reveals them at the cohort threshold', function () {
    $professor = User::factory()->create(['role' => 'professor']);
    [$quiz, $question, , $smallClass] = makeAnalyticsLayerReport($professor, 4);

    $report = app(AnalyticsService::class)->professorReport($professor, $quiz, ['class_id' => $smallClass->id]);
    expect($report['questions'][0]['suppressed'])->toBeTrue()
        ->and($report['questions'][0]['accuracy'])->toBeNull()
        ->and($report['score_distribution_suppressed'])->toBeTrue()
        ->and($report['topic_mastery'][0]['suppressed'])->toBeTrue()
        ->and($report['topic_mastery'][0]['accuracy'])->toBeNull();

    [$thresholdQuiz, , , $thresholdClass] = makeAnalyticsLayerReport($professor, 5);
    $thresholdReport = app(AnalyticsService::class)->professorReport($professor, $thresholdQuiz, ['class_id' => $thresholdClass->id]);
    expect($thresholdReport['questions'][0]['suppressed'])->toBeFalse()
        ->and($thresholdReport['questions'][0]['accuracy'])->toBe(60.0)
        ->and($thresholdReport['score_distribution_suppressed'])->toBeFalse()
        ->and($thresholdReport['topic_mastery'][0]['suppressed'])->toBeFalse()
        ->and($thresholdReport['topic_mastery'][0]['accuracy'])->toBe(60.0)
        ->and($thresholdReport['participation']['completed'])->toBe(5);
});

it('counts distinct students rather than repeated attempts for privacy thresholds', function () {
    $professor = User::factory()->create(['role' => 'professor']);
    [$quiz, $question, $students, $classroom] = makeAnalyticsLayerReport($professor, 1);
    $student = $students->first();

    for ($index = 1; $index <= 4; $index++) {
        $session = QuizSession::create([
            'quiz_id' => $quiz->id,
            'professor_id' => $professor->id,
            'status' => 'completed',
            'type' => 'live',
            'quiz_code' => 'QZ-REP'.$index,
        ]);
        SessionParticipant::create([
            'quiz_session_id' => $session->id,
            'student_id' => $student->id,
            'status' => 'completed',
            'score' => 1,
            'joined_at' => now(),
            'answers' => [(string) $question->id => 'A'],
        ]);
    }

    $report = app(AnalyticsService::class)->professorReport($professor, $quiz, ['class_id' => $classroom->id]);
    expect($report['participation']['completed'])->toBe(5)
        ->and($report['questions'][0]['suppressed'])->toBeTrue()
        ->and($report['score_distribution_suppressed'])->toBeTrue();
});

it('restricts professor reports and CSV exports to the quiz owner', function () {
    $owner = User::factory()->create(['role' => 'professor']);
    $otherProfessor = User::factory()->create(['role' => 'professor']);
    [$quiz, , , $classroom] = makeAnalyticsLayerReport($owner, 5);

    $this->actingAs($otherProfessor)
        ->get(route('professor.analytics.show', $quiz))
        ->assertForbidden();

    $this->actingAs($owner)
        ->get(route('professor.analytics.show', $quiz))
        ->assertOk()
        ->assertSee('Question-level diagnostics');

    $export = $this->get(route('professor.analytics.export', ['quiz' => $quiz->id, 'class_id' => $classroom->id]))
        ->assertOk();
    ob_start();
    $export->baseResponse->sendContent();
    $csv = ob_get_clean();
    expect($csv)->toContain('Score distribution', 'Most chosen distractor');

    $this->get(route('professor.analytics.export', ['quiz' => $quiz->id, 'class_id' => 99999]))
        ->assertNotFound();
});

it('shows only the signed-in student progress data and handles the empty state', function () {
    $student = User::factory()->create(['role' => 'student', 'is_whitelisted' => true]);
    $otherStudent = User::factory()->create(['role' => 'student', 'is_whitelisted' => true]);
    $professor = User::factory()->create(['role' => 'professor']);
    [$quiz] = makeAnalyticsLayerReport($professor, 1);

    $this->actingAs($student)
        ->get(route('student.progress'))
        ->assertOk()
        ->assertSee('Complete a quiz or run a coding challenge');

    $this->actingAs($student)
        ->get(route('professor.analytics.show', $quiz))
        ->assertForbidden();

    $attempt = QuizSession::create([
        'student_id' => $student->id,
        'language' => 'PHP',
        'topic' => 'Loops',
        'score' => 1,
        'status' => 'completed',
        'type' => 'self_study',
    ]);
    $attempt->questions()->create([
        'question_text' => 'Loop question',
        'options' => ['A', 'B'],
        'correct_answer' => 'A',
        'explanation' => 'A is correct.',
    ]);
    AnalyticsEvent::create([
        'user_id' => $student->id,
        'role' => 'student',
        'topic' => 'Loops',
        'language' => 'PHP',
        'event_type' => 'challenge_run',
        'payload' => [
            'concept' => 'Loops',
            'difficulty' => 'beginner',
            'passed' => false,
            'failed_test_category' => 'edge_case_assertion_failure',
        ],
        'occurred_at' => now(),
    ]);

    $this->actingAs($student)
        ->get(route('student.progress'))
        ->assertOk()
        ->assertSee('Loops')
        ->assertSee('Practice weakest topic')
        ->assertSee('Edge case assertion failure');

    $this->get(route('student.history', ['attempt_id' => $attempt->id, 'type' => 'self_study']))
        ->assertOk()
        ->assertSee('id="history-attempt-self_study-'.$attempt->id.'"', false)
        ->assertSee('open', false);

    expect(AnalyticsEvent::query()->where('user_id', $otherStudent->id)->count())->toBe(0);
});

it('records telemetry after the response without blocking the request', function () {
    $student = User::factory()->create(['role' => 'student']);

    app(AnalyticsService::class)->record([
        'user_id' => $student->id,
        'role' => $student->role,
        'session_id' => 42,
        'topic' => 'Arrays',
        'language' => 'PHP',
        'event_type' => 'attempt_completed',
        'payload' => ['question_count' => 3],
    ]);

    expect(AnalyticsEvent::query()->count())->toBe(0);
    app()->terminate();

    expect(AnalyticsEvent::query()->count())->toBe(1)
        ->and(AnalyticsEvent::query()->first()->payload['question_count'])->toBe(3);
});

it('records self-study answer and completion events with response-time data', function () {
    $student = User::factory()->create(['role' => 'student', 'is_whitelisted' => true]);
    $session = QuizSession::create([
        'student_id' => $student->id,
        'language' => 'PHP',
        'topic' => 'Arrays',
        'status' => 'active',
        'type' => 'self_study',
    ]);
    $question = $session->questions()->create([
        'question_text' => 'Pick the correct option.',
        'options' => ['A', 'B'],
        'correct_answer' => 'A',
        'explanation' => 'A is correct.',
    ]);

    $this->actingAs($student)
        ->postJson(route('student.quiz.submit', $session), [
            'answers' => [$question->id => 'B'],
            'response_times' => [$question->id => 1250],
        ])
        ->assertOk();
    app()->terminate();

    $answerEvent = AnalyticsEvent::query()->where('event_type', 'answer_submitted')->firstOrFail();
    $completionEvent = AnalyticsEvent::query()->where('event_type', 'attempt_completed')->firstOrFail();
    expect($answerEvent->payload['response_time_ms'])->toBe(1250)
        ->and($answerEvent->payload['selected_option'])->toBe('B')
        ->and($completionEvent->payload['attempt_type'])->toBe('self_study');
});

it('records live answer changes and challenge test-run categories', function () {
    $student = User::factory()->create(['role' => 'student', 'is_whitelisted' => true]);
    $professor = User::factory()->create(['role' => 'professor']);
    $quiz = Quiz::create([
        'professor_id' => $professor->id,
        'title' => 'Live event quiz',
        'question_count' => 1,
        'status' => 'ready',
    ]);
    $question = $quiz->questions()->create([
        'question_text' => 'Pick one.',
        'options' => ['A', 'B'],
        'correct_answer' => 'A',
        'explanation' => 'A is correct.',
    ]);
    $liveSession = QuizSession::create([
        'quiz_id' => $quiz->id,
        'professor_id' => $professor->id,
        'status' => 'in_progress',
        'type' => 'live',
        'quiz_code' => 'QZ-EVENTS',
    ]);
    SessionParticipant::create([
        'quiz_session_id' => $liveSession->id,
        'student_id' => $student->id,
        'status' => 'joined',
    ]);

    $this->actingAs($student)
        ->postJson(route('student.live.progress', $liveSession), [
            'question_id' => $question->id,
            'answer' => 'A',
            'response_time_ms' => 1800,
        ])
        ->assertOk();
    app()->terminate();

    $this->postJson(route('student.live.progress', $liveSession), [
        'question_id' => $question->id,
        'answer' => 'B',
        'response_time_ms' => 2400,
    ])->assertOk();
    app()->terminate();

    $challenge = CodeChallenge::create([
        'slug' => 'analytics-challenge',
        'title' => 'Array traversal',
        'description' => 'Return a result for each input value.',
        'difficulty' => 'beginner',
        'function_name' => 'transformValues',
        'starter_code' => '<?php',
        'test_cases' => [['label' => 'sample', 'arguments' => [[1]], 'expected' => [1]]],
        'badge_name' => 'Array badge',
        'is_active' => true,
    ]);
    config([
        'services.code_runner.url' => 'http://127.0.0.1:8090',
        'services.code_runner.token' => str_repeat('test-token-', 4),
    ]);
    Http::fake([
        'http://127.0.0.1:8090/run' => Http::response([
            'passed' => false,
            'results' => [['label' => 'sample', 'passed' => false]],
            'output' => '1 of 1 tests failed.',
        ]),
    ]);

    $this->postJson(route('student.challenges.run', $challenge), ['code' => '<?php'])
        ->assertOk()
        ->assertJsonPath('passed', false);
    app()->terminate();

    expect(AnalyticsEvent::query()->where('event_type', 'answer_changed')->exists())->toBeTrue()
        ->and(AnalyticsEvent::query()->where('event_type', 'challenge_run')->firstOrFail()->payload['failed_test_category'])
        ->toBe('edge_case_assertion_failure');
});
