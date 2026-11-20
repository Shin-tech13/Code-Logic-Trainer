<?php

use App\Models\Quiz;
use App\Models\QuizSession;
use App\Models\SessionParticipant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('student can view private profile details without exposing them in the directory', function () {
    $professor = User::factory()->create(['role' => 'professor']);
    $student = User::factory()->create([
        'name' => 'Profile Student',
        'email' => 'profile.student@codelogic.com',
        'personal_email' => 'profile.private@example.test',
        'age' => 20,
        'year_level' => '2nd Year',
        'student_id' => 'STU-2001',
        'is_whitelisted' => true,
        'created_by_user_id' => $professor->id,
    ]);
    $classroom = $professor->classrooms()->create([
        'title' => 'BSIT 2A',
        'class_code' => 'BSIT-2A-01',
    ]);
    $student->enrolledClassrooms()->attach($classroom);

    $this->actingAs($student)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertSee('profile.student@codelogic.com')
        ->assertSee('profile.private@example.test')
        ->assertSee('20')
        ->assertSee('2nd Year')
        ->assertSee('STU-2001')
        ->assertSee('BSIT 2A');

    $this->actingAs($professor)
        ->get(route('users.index'))
        ->assertOk()
        ->assertDontSee('profile.private@example.test');
});

test('professor can manage only their connected student and cannot change the student role', function () {
    $professor = User::factory()->create(['role' => 'professor']);
    $otherProfessor = User::factory()->create(['role' => 'professor']);
    $student = User::factory()->create([
        'role' => 'student',
        'personal_email' => 'managed.student@example.test',
        'created_by_user_id' => $professor->id,
        'year_level' => null,
        'student_id' => null,
    ]);
    $unrelatedStudent = User::factory()->create(['role' => 'student']);
    $foreignClassroom = $otherProfessor->classrooms()->create([
        'title' => 'Other class',
        'class_code' => 'OTHER-2001',
    ]);

    $this->actingAs($professor)
        ->get(route('professor.students.edit', $student))
        ->assertOk()
        ->assertSee('Student ID');

    $this->actingAs($professor)
        ->patch(route('professor.students.update', $student), [
            'name' => 'Updated Student',
            'personal_email' => $student->personal_email,
            'age' => '21',
            'year_level' => '3rd Year',
            'student_id' => 'STU-3001',
            'role' => 'admin',
        ])
        ->assertRedirect(route('professor.students.edit', $student));

    expect($student->fresh()->name)->toBe('Updated Student')
        ->and($student->fresh()->age)->toBe(21)
        ->and($student->fresh()->year_level)->toBe('3rd Year')
        ->and($student->fresh()->student_id)->toBe('STU-3001')
        ->and($student->fresh()->role)->toBe('student');

    $this->actingAs($professor)
        ->patch(route('professor.students.update', $student), [
            'name' => 'Updated Student',
            'personal_email' => $student->personal_email,
            'year_level' => '3rd Year',
            'student_id' => 'STU-3001',
            'classroom_id' => $foreignClassroom->id,
        ])
        ->assertForbidden();

    $this->actingAs($otherProfessor)
        ->get(route('professor.students.edit', $student))
        ->assertForbidden();

    $this->actingAs($professor)
        ->get(route('professor.students.edit', $unrelatedStudent))
        ->assertForbidden();
});

test('students can view only their own completed quiz history and explanations', function () {
    $student = User::factory()->create(['role' => 'student', 'is_whitelisted' => true]);
    $otherStudent = User::factory()->create(['role' => 'student', 'is_whitelisted' => true]);

    $session = QuizSession::create([
        'student_id' => $student->id,
        'language' => 'PHP',
        'topic' => 'Arrays',
        'status' => 'completed',
        'score' => 0,
        'answers' => [],
    ]);
    $session->questions()->create([
        'question_text' => 'Which value is correct?',
        'options' => ['Right', 'Wrong'],
        'correct_answer' => 'Right',
        'explanation' => '',
    ]);
    $otherSession = QuizSession::create([
        'student_id' => $otherStudent->id,
        'language' => 'Java',
        'topic' => 'Private topic',
        'status' => 'completed',
        'score' => 0,
        'answers' => [],
    ]);
    $otherSession->questions()->create([
        'question_text' => 'Other student private question',
        'options' => ['A', 'B'],
        'correct_answer' => 'A',
        'explanation' => '',
    ]);

    $this->actingAs($student)
        ->get(route('student.history'))
        ->assertOk()
        ->assertSee('PHP: Arrays')
        ->assertSee('Which value is correct?')
        ->assertSee('No answer submitted')
        ->assertSee('Right')
        ->assertSee('No explanation provided.')
        ->assertDontSee('Private topic')
        ->assertDontSee('Other student private question');
});

test('student history includes completed live quiz answers', function () {
    $student = User::factory()->create(['role' => 'student', 'is_whitelisted' => true]);
    $professor = User::factory()->create(['role' => 'professor']);
    $quiz = Quiz::create([
        'professor_id' => $professor->id,
        'title' => 'Midterm Review',
        'question_count' => 1,
        'status' => 'ready',
    ]);
    $question = $quiz->questions()->create([
        'question_text' => 'Select the correct answer.',
        'options' => ['Correct', 'Incorrect'],
        'correct_answer' => 'Correct',
        'explanation' => 'Review the lesson.',
    ]);
    $session = QuizSession::create([
        'quiz_id' => $quiz->id,
        'professor_id' => $professor->id,
        'type' => 'live',
        'status' => 'completed',
        'quiz_code' => 'LIV-12345',
    ]);
    SessionParticipant::create([
        'quiz_session_id' => $session->id,
        'student_id' => $student->id,
        'status' => 'completed',
        'score' => 0,
        'answers' => [$question->id => 'Incorrect'],
        'joined_at' => now()->subMinutes(10),
    ]);

    $this->actingAs($student)
        ->get(route('student.history'))
        ->assertOk()
        ->assertSee('Midterm Review')
        ->assertSee('Incorrect')
        ->assertSee('Correct')
        ->assertSee('Review the lesson.');
});

test('student history summarizes attempts and safely formats fenced code in answer reviews', function () {
    $student = User::factory()->create(['role' => 'student', 'is_whitelisted' => true]);
    $session = QuizSession::create([
        'student_id' => $student->id,
        'language' => 'PHP',
        'topic' => 'Loops',
        'status' => 'completed',
        'score' => 1,
        'answers' => [],
    ]);
    $correctQuestion = $session->questions()->create([
        'question_text' => "What does this print?\n```php\nprint('ready');\n```",
        'options' => ['ready', 'done'],
        'correct_answer' => 'ready',
        'explanation' => "The output is:\n```text\nready\n```",
    ]);
    $incorrectQuestion = $session->questions()->create([
        'question_text' => '<script>alert("unsafe")</script>',
        'options' => ['A', 'B'],
        'correct_answer' => 'A',
        'explanation' => '<img src=x onerror=alert(1)>',
    ]);
    $session->update([
        'answers' => [
            $correctQuestion->id => 'ready',
            $incorrectQuestion->id => 'B',
        ],
    ]);

    $this->actingAs($student)
        ->get(route('student.history'))
        ->assertOk()
        ->assertSee('Score: 1 / 2')
        ->assertSee('Correct: 1')
        ->assertSee('Incorrect: 1')
        ->assertSee('Incorrect answers (1)')
        ->assertSee('Review all answers (2)')
        ->assertSee('<pre', false)
        ->assertSee('print(&#039;ready&#039;);', false)
        ->assertSee('&lt;script&gt;alert(&quot;unsafe&quot;)&lt;/script&gt;', false)
        ->assertSee('&lt;img src=x onerror=alert(1)&gt;', false)
        ->assertDontSee('<script>alert("unsafe")</script>', false)
        ->assertDontSee('<img src=x onerror=alert(1)>', false);
});

test('student history filters attempts and paginates results', function () {
    $student = User::factory()->create(['role' => 'student', 'is_whitelisted' => true]);

    foreach (range(1, 12) as $number) {
        $session = QuizSession::create([
            'student_id' => $student->id,
            'language' => 'PHP',
            'topic' => "Topic {$number}",
            'type' => 'self_study',
            'status' => 'completed',
            'score' => 0,
        ]);
        QuizSession::whereKey($session->id)->update([
            'updated_at' => now()->subDays($number),
        ]);
    }

    $this->actingAs($student)
        ->get(route('student.history', [
            'type' => 'self_study',
            'date_from' => now()->subDays(10)->toDateString(),
        ]))
        ->assertOk()
        ->assertSee('Topic 1')
        ->assertSee('Topic 10')
        ->assertDontSee('Topic 11')
        ->assertDontSee('Topic 12');

    $this->get(route('student.history', ['type' => 'self_study']))
        ->assertOk()
        ->assertSee('Topic 1')
        ->assertSee('Topic 10')
        ->assertDontSee('Topic 11')
        ->assertSee('page=2', false);

    $this->get(route('student.history', [
        'type' => 'self_study',
        'page' => 2,
    ]))
        ->assertOk()
        ->assertSee('Topic 11')
        ->assertSee('Topic 12')
        ->assertDontSee('PHP: Topic 1</span>', false);
});
