<?php

use App\Models\Quiz;
use App\Models\QuizSession;
use App\Models\SessionParticipant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createRosterTestQuiz(User $professor, string $title = 'Roster quiz'): Quiz
{
    $quiz = Quiz::create([
        'professor_id' => $professor->id,
        'title' => $title,
        'question_count' => 1,
        'status' => 'ready',
    ]);

    $quiz->questions()->create([
        'question_text' => 'Which value is correct?',
        'options' => ['A', 'B'],
        'correct_answer' => 'A',
        'explanation' => 'A is correct.',
    ]);

    return $quiz;
}

test('session setup reuses owned classrooms and whitelist and defaults to a normal whitelist session', function () {
    /** @var \Tests\TestCase $this */
    $professor = User::factory()->create(['role' => 'professor']);
    $otherProfessor = User::factory()->create(['role' => 'professor']);
    $professor->whitelistedStudents()->create([
        'name' => 'Roster One',
        'email' => 'ROSTER.ONE@example.test',
        'status' => 'active',
    ]);
    $otherProfessor->whitelistedStudents()->create([
        'name' => 'Private Student',
        'email' => 'private.student@example.test',
        'status' => 'active',
    ]);
    $quiz = createRosterTestQuiz($professor);

    $this->actingAs($professor)
        ->get(route('professor.sessions.create', $quiz))
        ->assertOk()
        ->assertSee('Saved class')
        ->assertSee('Manual / specific students')
        ->assertSee('All whitelisted students')
        ->assertSee('roster.one@example.test')
        ->assertDontSee('private.student@example.test');

    $this->post(route('professor.sessions.launch', $quiz))
        ->assertRedirect();

    $session = QuizSession::query()->where('quiz_id', $quiz->id)->firstOrFail();
    expect($session->is_make_up)->toBeFalse()
        ->and($session->make_up_label)->toBeNull()
        ->and($session->roster_mode)->toBe('all_whitelisted')
        ->and($session->invitees()->pluck('email')->all())->toBe(['roster.one@example.test']);
});

test('saved class selection snapshots its students and optional make-up label', function () {
    /** @var \Tests\TestCase $this */
    $professor = User::factory()->create(['role' => 'professor']);
    $otherProfessor = User::factory()->create(['role' => 'professor']);
    $classroom = $professor->classrooms()->create([
        'title' => 'Programming 2A',
        'class_code' => 'P2A-1234',
    ]);
    $foreignClassroom = $otherProfessor->classrooms()->create([
        'title' => 'Foreign class',
        'class_code' => 'FCL-1234',
    ]);
    $student = User::factory()->create([
        'name' => 'Kai Student',
        'personal_email' => 'Kai.Student@example.test',
        'is_whitelisted' => false,
    ]);
    $foreignStudent = User::factory()->create([
        'personal_email' => 'foreign@example.test',
    ]);
    $classroom->students()->attach($student->id);
    $foreignClassroom->students()->attach($foreignStudent->id);
    $quiz = createRosterTestQuiz($professor);

    $this->actingAs($professor)
        ->post(route('professor.sessions.launch', $quiz), [
            'roster_mode' => 'saved_class',
            'classroom_id' => $classroom->id,
            'is_make_up' => '1',
            'make_up_label' => 'Friday sitting',
        ])
        ->assertRedirect();

    $session = QuizSession::query()->where('quiz_id', $quiz->id)->firstOrFail();
    expect($session->is_make_up)->toBeTrue()
        ->and($session->make_up_label)->toBe('Friday sitting')
        ->and($session->classroom_id)->toBe($classroom->id)
        ->and($session->invitees()->pluck('email')->all())->toBe(['kai.student@example.test']);

    $this->get(route('professor.sessions.lobby', $session))
        ->assertOk()
        ->assertSee('Make-up session')
        ->assertSee('Friday sitting');

    $foreignClassQuiz = createRosterTestQuiz($professor, 'Foreign class validation');
    $this->post(route('professor.sessions.launch', $foreignClassQuiz), [
        'roster_mode' => 'saved_class',
        'classroom_id' => $foreignClassroom->id,
    ])->assertForbidden();
    expect(QuizSession::query()->where('quiz_id', $foreignClassQuiz->id)->exists())->toBeFalse();

    $this->actingAs($student)
        ->post(route('student.live.join.store'), ['quiz_code' => $session->quiz_code])
        ->assertRedirect(route('student.live.lobby', $session));

    $this->get(route('student.live.lobby', $session))->assertOk();
});

test('manual roster combines managed selections and pasted emails with case-insensitive normalization', function () {
    /** @var \Tests\TestCase $this */
    $professor = User::factory()->create(['role' => 'professor']);
    $registered = User::factory()->create([
        'name' => 'Registered Invitee',
        'personal_email' => 'registered@example.test',
        'is_whitelisted' => true,
    ]);
    $professor->whitelistedStudents()->create([
        'name' => 'Registered Invitee',
        'email' => 'registered@example.test',
        'status' => 'active',
    ]);
    $quiz = createRosterTestQuiz($professor);

    $this->actingAs($professor)
        ->post(route('professor.sessions.launch', $quiz), [
            'roster_mode' => 'manual',
            'roster_emails' => ['REGISTERED@example.test'],
            'manual_emails' => " OneOff@Example.test\n",
            'make_up_label' => 'Ignored while toggle is off',
        ])
        ->assertRedirect();

    $session = QuizSession::query()->where('quiz_id', $quiz->id)->firstOrFail();
    $invitees = $session->invitees()->orderBy('email')->get()->keyBy('email');
    expect($session->roster_mode)->toBe('manual')
        ->and($session->is_make_up)->toBeFalse()
        ->and($session->make_up_label)->toBeNull()
        ->and($invitees->keys()->all())->toBe(['oneoff@example.test', 'registered@example.test'])
        ->and($invitees->get('registered@example.test')->user_id)->toBe($registered->id)
        ->and($invitees->get('oneoff@example.test')->user_id)->toBeNull();

    $foreignProfessor = User::factory()->create(['role' => 'professor']);
    $foreignQuiz = createRosterTestQuiz($foreignProfessor, 'Foreign quiz');
    $this->actingAs($foreignProfessor)
        ->post(route('professor.sessions.launch', $foreignQuiz), [
            'roster_mode' => 'manual',
            'roster_emails' => ['someone-elses@example.test'],
        ])
        ->assertSessionHasErrors('roster_emails');
});

test('session roster guards join, quiz start, heartbeat, answers, and anti-cheat while keeping session history', function () {
    /** @var \Tests\TestCase $this */
    $professor = User::factory()->create(['role' => 'professor']);
    $invited = User::factory()->create([
        'name' => 'Make-up Student',
        'personal_email' => 'MAKEUP@example.test',
        'is_whitelisted' => false,
    ]);
    $outsider = User::factory()->create([
        'personal_email' => 'outsider@example.test',
        'is_whitelisted' => false,
    ]);
    $quiz = createRosterTestQuiz($professor, 'Specific roster quiz');

    $this->actingAs($professor)
        ->post(route('professor.sessions.launch', $quiz), [
            'roster_mode' => 'manual',
            'manual_emails' => 'makeup@example.test',
        ])
        ->assertRedirect();

    $session = QuizSession::query()->where('quiz_id', $quiz->id)->firstOrFail();
    $question = $quiz->questions()->firstOrFail();

    $this->actingAs($outsider)
        ->post(route('student.live.join.store'), ['quiz_code' => $session->quiz_code])
        ->assertSessionHasErrors('quiz_code');
    expect($session->participants()->count())->toBe(0);

    $this->actingAs($invited)
        ->post(route('student.live.join.store'), ['quiz_code' => $session->quiz_code])
        ->assertRedirect(route('student.live.lobby', $session));
    $participant = SessionParticipant::query()
        ->where('quiz_session_id', $session->id)
        ->where('student_id', $invited->id)
        ->firstOrFail();

    $this->actingAs($professor)
        ->postJson(route('professor.sessions.start', $session))
        ->assertOk()
        ->assertJsonPath('success', true);

    $this->actingAs($invited)
        ->get(route('student.live.take', $session))
        ->assertOk()
        ->assertSee('heartbeat');

    $this->postJson(route('student.live.heartbeat', $session))
        ->assertOk()
        ->assertJsonPath('alive', true);
    $this->postJson(route('student.live.flag-tab-switch', $session))
        ->assertOk()
        ->assertJsonPath('flagged', true);
    $this->postJson(route('student.live.progress', $session), [
        'question_id' => $question->id,
        'answer' => 'A',
        'response_time_ms' => 900,
    ])->assertOk()->assertJsonPath('saved', true);
    $this->postJson(route('student.live.submit', $session), [
        'answers' => [(string) $question->id => 'A'],
    ])->assertOk()->assertJsonPath('success', true);

    $this->actingAs($outsider)
        ->getJson(route('student.live.status', $session))
        ->assertForbidden();
    $this->get(route('student.live.take', $session))->assertForbidden();
    $this->postJson(route('student.live.heartbeat', $session))->assertForbidden();
    $this->postJson(route('student.live.flag-tab-switch', $session))->assertForbidden();
    $this->postJson(route('student.live.progress', $session), [
        'question_id' => $question->id,
        'answer' => 'A',
    ])->assertForbidden();
    $this->postJson(route('student.live.submit', $session), [
        'answers' => [(string) $question->id => 'A'],
    ])->assertForbidden();

    $this->actingAs($professor)
        ->post(route('professor.sessions.end', $session))
        ->assertRedirect();

    expect($participant->fresh()->status)->toBe('completed')
        ->and($participant->fresh()->tab_switch_count)->toBe(1)
        ->and($participant->fresh()->last_active_at)->not->toBeNull()
        ->and($session->fresh()->invitees()->count())->toBe(1);
});
