<?php

use App\Models\ClassroomEmailAssignment;
use App\Models\Quiz;
use App\Models\QuizSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

test('professors can assign and reassign whitelisted emails and filter class invitations', function () {
    /** @var \Tests\TestCase $this */
    $professor = User::factory()->create(['role' => 'professor']);
    $otherProfessor = User::factory()->create(['role' => 'professor']);
    $classA = $professor->classrooms()->create(['title' => 'BSIT -3A', 'class_code' => 'A-1000']);
    $classB = $professor->classrooms()->create(['title' => 'BSIT 2 C', 'class_code' => 'B-2000']);
    $foreignClass = $otherProfessor->classrooms()->create(['title' => 'Foreign class', 'class_code' => 'F-3000']);

    $professor->whitelistedStudents()->create([
        'name' => 'Pre-registration Student',
        'email' => 'pre.reg@example.test',
        'status' => 'active',
    ]);
    $professor->whitelistedStudents()->create([
        'name' => 'Another Student',
        'email' => 'another@example.test',
        'status' => 'active',
    ]);

    $this->actingAs($professor)
        ->post(route('professor.classrooms.students.assign'), [
            'classroom_id' => $classA->id,
            'email' => 'PRE.REG@example.test',
        ])
        ->assertRedirect(route('professor.students.index', ['classroom_id' => $classA->id]));

    $this->post(route('professor.classrooms.students.assign'), [
        'classroom_id' => $classB->id,
        'email' => 'another@example.test',
    ])->assertRedirect(route('professor.students.index', ['classroom_id' => $classB->id]));

    $classAResponse = $this->get(route('professor.students.index', ['classroom_id' => $classA->id]))
        ->assertOk()
        ->assertSee('Pre-registration');
    $tableBody = explode('<tbody', $classAResponse->getContent(), 2)[1] ?? '';
    $tableBody = explode('</tbody>', $tableBody, 2)[0] ?? '';
    expect($tableBody)
        ->toContain('pre.reg@example.test')
        ->not->toContain('another@example.test');
    $this->get(route('professor.students.index', ['classroom_id' => $foreignClass->id]))
        ->assertNotFound();

    $this->actingAs($professor)
        ->post(route('professor.classrooms.students.assign'), [
            'classroom_id' => $classB->id,
            'email' => 'pre.reg@example.test',
        ])
        ->assertRedirect(route('professor.students.index', ['classroom_id' => $classB->id]));

    $pendingAssignment = ClassroomEmailAssignment::query()
        ->where('email', 'pre.reg@example.test')
        ->firstOrFail();
    expect(ClassroomEmailAssignment::query()->count())->toBe(2)
        ->and(ClassroomEmailAssignment::query()->where('classroom_id', $classA->id)->count())->toBe(0)
        ->and(ClassroomEmailAssignment::query()->where('classroom_id', $classB->id)->count())->toBe(2);

    $this->actingAs($otherProfessor)
        ->delete(route('professor.classroom-email-assignments.destroy', $pendingAssignment))
        ->assertForbidden();
    $this->actingAs($professor)
        ->post(route('professor.classrooms.students.assign'), [
            'classroom_id' => $foreignClass->id,
            'email' => 'pre.reg@example.test',
        ])
        ->assertForbidden();
});

test('registered managed students move between professor classes without losing other class memberships', function () {
    /** @var \Tests\TestCase $this */
    $professor = User::factory()->create(['role' => 'professor']);
    $otherProfessor = User::factory()->create(['role' => 'professor']);
    $classA = $professor->classrooms()->create(['title' => 'BSIT 2D', 'class_code' => 'D-4000']);
    $classB = $professor->classrooms()->create(['title' => 'BSIT 3A', 'class_code' => 'E-5000']);
    $foreignClass = $otherProfessor->classrooms()->create(['title' => 'Other class', 'class_code' => 'O-6000']);
    $student = User::factory()->create([
        'role' => 'student',
        'personal_email' => 'managed@example.test',
        'created_by_user_id' => $professor->id,
    ]);
    $student->enrolledClassrooms()->attach([$classA->id, $foreignClass->id]);

    $this->actingAs($professor)
        ->post(route('professor.classrooms.students.assign'), [
            'classroom_id' => $classB->id,
            'email' => 'managed@example.test',
        ])
        ->assertRedirect(route('professor.students.index', ['classroom_id' => $classB->id]));

    expect($student->enrolledClassrooms()->pluck('classrooms.id')->all())
        ->toContain($classB->id)
        ->toContain($foreignClass->id)
        ->not->toContain($classA->id);
});

test('registration converts a case-insensitive pre-registration class assignment to the existing student pivot', function () {
    /** @var \Tests\TestCase $this */
    Notification::fake();
    $professor = User::factory()->create(['role' => 'professor']);
    $classroom = $professor->classrooms()->create([
        'title' => 'BSIT 2 C',
        'class_code' => 'C-7000',
    ]);
    $professor->whitelistedStudents()->create([
        'name' => 'New Student',
        'email' => 'NEW.STUDENT@example.test',
        'status' => 'active',
    ]);

    $this->actingAs($professor)
        ->post(route('professor.classrooms.students.assign'), [
            'classroom_id' => $classroom->id,
            'email' => 'new.student@example.test',
        ])
        ->assertRedirect();

    Auth::logout();
    $this->flushSession();

    $this->post(route('register'), [
        'name' => 'New Student',
        'personal_email' => 'NEW.STUDENT@example.test',
        'year_level' => '2nd Year',
        'student_id' => 'ASSIGN-001',
        'password' => 'StrongPassword123!',
        'password_confirmation' => 'StrongPassword123!',
        'terms' => '1',
    ])->assertRedirect(route('register.confirmation'));

    $student = User::query()->where('personal_email', 'new.student@example.test')->firstOrFail();
    expect($student->enrolledClassrooms()->pluck('classrooms.id')->all())->toBe([$classroom->id])
        ->and(ClassroomEmailAssignment::query()->count())->toBe(0)
        ->and($student->created_by_user_id)->toBe($professor->id);
});

test('saved class session snapshots pre-registration class email assignments', function () {
    /** @var \Tests\TestCase $this */
    $professor = User::factory()->create(['role' => 'professor']);
    $classroom = $professor->classrooms()->create([
        'title' => 'BSIT 2D',
        'class_code' => 'D-8000',
    ]);
    $professor->whitelistedStudents()->create([
        'name' => 'Waiting Student',
        'email' => 'waiting@example.test',
        'status' => 'active',
    ]);
    $this->actingAs($professor)
        ->post(route('professor.classrooms.students.assign'), [
            'classroom_id' => $classroom->id,
            'email' => 'waiting@example.test',
        ])
        ->assertRedirect();

    $quiz = Quiz::create([
        'professor_id' => $professor->id,
        'title' => 'Class roster quiz',
        'question_count' => 1,
        'status' => 'ready',
    ]);
    $quiz->questions()->create([
        'question_text' => 'Which value is correct?',
        'options' => ['A', 'B'],
        'correct_answer' => 'A',
        'explanation' => 'A is correct.',
    ]);

    $this->post(route('professor.sessions.launch', $quiz), [
        'roster_mode' => 'saved_class',
        'classroom_id' => $classroom->id,
    ])->assertRedirect();

    $session = QuizSession::query()->where('quiz_id', $quiz->id)->firstOrFail();
    expect($session->invitees()->pluck('email')->all())->toBe(['waiting@example.test']);
});
