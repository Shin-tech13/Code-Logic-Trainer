<?php

use App\Models\User;
use App\Models\WhitelistedStudent;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('admin can create professor and student accounts with trusted creator attribution', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->post(route('admin.users.store'), [
            'name' => 'New Professor',
            'email' => 'professor.new@example.test',
            'role' => 'professor',
            'password' => 'StrongPassword123!',
            'password_confirmation' => 'StrongPassword123!',
            'created_by_user_id' => 99999,
        ])
        ->assertRedirect(route('users.index'));

    $professor = User::where('email', 'professor.new@example.test')->firstOrFail();
    expect($professor->role)->toBe('professor')
        ->and($professor->created_by_user_id)->toBe($admin->id)
        ->and($professor->is_whitelisted)->toBeFalse();

    $this->actingAs($admin)
        ->post(route('admin.users.store'), [
            'name' => 'New Student',
            'email' => 'student.new@example.test',
            'role' => 'student',
            'password' => 'StrongPassword123!',
            'password_confirmation' => 'StrongPassword123!',
        ])
        ->assertRedirect(route('users.index'));

    $student = User::where('email', 'student.new@example.test')->firstOrFail();
    expect($student->role)->toBe('student')
        ->and($student->created_by_user_id)->toBe($admin->id)
        ->and($student->is_whitelisted)->toBeTrue();
});

test('only admins may create accounts and students cannot view the directory', function () {
    $professor = User::factory()->create(['role' => 'professor']);
    $student = User::factory()->create(['role' => 'student', 'is_whitelisted' => true]);

    $this->actingAs($professor)
        ->get(route('users.index'))
        ->assertOk()
        ->assertDontSee(route('admin.users.create'));

    $this->actingAs($professor)
        ->post(route('admin.users.store'), [])
        ->assertForbidden();

    $this->actingAs($student)
        ->get(route('users.index'))
        ->assertForbidden();

    $this->actingAs($student)
        ->get(route('admin.users.create'))
        ->assertForbidden();

    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin)
        ->get(route('student.dashboard'))
        ->assertForbidden();
});

test('admin can only create professor or student roles', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->from(route('admin.users.create'))
        ->post(route('admin.users.store'), [
            'name' => 'Escalation Attempt',
            'email' => 'admin.extra@example.test',
            'role' => 'admin',
            'password' => 'StrongPassword123!',
            'password_confirmation' => 'StrongPassword123!',
        ])
        ->assertSessionHasErrors('role');

    expect(User::where('email', 'admin.extra@example.test')->exists())->toBeFalse();
});

test('admin and professor can filter the directory by role and creator', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $professor = User::factory()->create(['role' => 'professor']);
    $student = User::factory()->create(['role' => 'student', 'is_whitelisted' => true]);
    $otherStudent = User::factory()->create(['role' => 'student', 'is_whitelisted' => true]);
    $student->createdBy()->associate($professor)->save();

    $this->actingAs($admin)
        ->get(route('users.index', ['role' => 'student', 'created_by' => $professor->id]))
        ->assertOk()
        ->assertSee($student->email)
        ->assertSee($professor->email)
        ->assertDontSee($otherStudent->email);

    $this->actingAs($professor)
        ->get(route('users.index', ['role' => 'student', 'created_by' => $professor->id]))
        ->assertOk()
        ->assertSee($student->email);
});

test('directory filters reject invalid roles and creator ids', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $systemUser = User::factory()->create(['role' => 'student', 'is_whitelisted' => true]);

    $this->actingAs($admin)
        ->get(route('users.index', ['role' => 'owner']))
        ->assertSessionHasErrors('role');

    $this->actingAs($admin)
        ->get(route('users.index', ['created_by' => 999999]))
        ->assertSessionHasErrors('created_by');

    $this->actingAs($admin)
        ->get(route('users.index', ['created_by' => 'system']))
        ->assertOk()
        ->assertSee($systemUser->email);
});

test('whitelisted student registration records the professor who added the email', function () {
    $professor = User::factory()->create(['role' => 'professor']);
    WhitelistedStudent::create([
        'professor_id' => $professor->id,
        'name' => 'New Student',
        'email' => 'registered.student@example.test',
        'status' => 'active',
    ]);

    $this->post(route('register'), [
        'name' => 'New Student',
        'email' => 'registered.student@example.test',
        'password' => 'StrongPassword123!',
        'password_confirmation' => 'StrongPassword123!',
    ])->assertRedirect(route('dashboard'));

    $student = User::where('email', 'registered.student@example.test')->firstOrFail();
    expect($student->created_by_user_id)->toBe($professor->id)
        ->and($student->is_whitelisted)->toBeTrue();
});

test('admin login redirects to user management without student whitelist requirements', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
        'is_whitelisted' => false,
        'password' => bcrypt('StrongPassword123!'),
    ]);

    $this->post(route('login'), [
        'email' => $admin->email,
        'password' => 'StrongPassword123!',
    ])->assertRedirect(route('users.index'));
});

test('default account seeder creates four hashed accounts idempotently', function () {
    config(['default_accounts' => [
        [
            'name' => 'Admin One',
            'email' => 'admin1@codelogic.com',
            'role' => 'admin',
            'password' => 'FirstUniquePassword123!',
        ],
        [
            'name' => 'Admin Two',
            'email' => 'admin2@codelogic.com',
            'role' => 'admin',
            'password' => 'SecondUniquePassword123!',
        ],
        [
            'name' => 'Student User',
            'email' => 'student@codelogic.com',
            'role' => 'student',
            'password' => 'StudentUniquePassword123!',
        ],
        [
            'name' => 'Professor User',
            'email' => 'professor@codelogic.com',
            'role' => 'professor',
            'password' => 'ProfessorUniquePassword123!',
        ],
    ]]);

    app(\Database\Seeders\DatabaseSeeder::class)->run();

    $first = User::where('email', 'admin1@codelogic.com')->firstOrFail();
    $second = User::where('email', 'admin2@codelogic.com')->firstOrFail();
    $student = User::where('email', 'student@codelogic.com')->firstOrFail();
    $professor = User::where('email', 'professor@codelogic.com')->firstOrFail();
    $studentPasswordHash = $student->password;

    config(['default_accounts.2.password' => 'ChangedStudentPassword123!']);
    app(\Database\Seeders\DatabaseSeeder::class)->run();

    expect($first->isAdmin())->toBeTrue()
        ->and($second->isAdmin())->toBeTrue()
        ->and($first->password)->not->toBe('FirstUniquePassword123!')
        ->and($second->password)->not->toBe('SecondUniquePassword123!')
        ->and($student->isStudent())->toBeTrue()
        ->and($student->is_whitelisted)->toBeTrue()
        ->and($student->password)->toBe($studentPasswordHash)
        ->and($professor->isProfessor())->toBeTrue()
        ->and(User::whereIn('email', [
            'admin1@codelogic.com',
            'admin2@codelogic.com',
            'student@codelogic.com',
            'professor@codelogic.com',
        ])->count())->toBe(4);
});
