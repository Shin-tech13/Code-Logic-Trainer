<?php

use App\Models\User;
use App\Notifications\AccountApproved;
use App\Notifications\AccountPendingApproval;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

test('public registration creates a pending student with a generated login and private contact', function () {
    Notification::fake();

    $this->post(route('register'), [
        'name' => 'Jane Doe',
        'personal_email' => 'jane.doe@example.test',
        'year_level' => '2nd Year',
        'student_id' => '2026-001',
        'password' => 'StrongPassword123!',
        'password_confirmation' => 'StrongPassword123!',
        'role' => 'admin',
        'account_status' => 'approved',
        'is_whitelisted' => true,
    ])->assertRedirect(route('register.confirmation'))
        ->assertSessionHas('registration_login_address', 'janedoe@codelogic.com');

    $user = User::where('email', 'janedoe@codelogic.com')->firstOrFail();

    expect($user->personal_email)->toBe('jane.doe@example.test')
        ->and($user->year_level)->toBe('2nd Year')
        ->and($user->student_id)->toBe('2026-001')
        ->and($user->role)->toBe('student')
        ->and($user->account_status)->toBe('pending')
        ->and($user->is_whitelisted)->toBeFalse()
        ->and(Hash::check('StrongPassword123!', $user->password))->toBeTrue()
        ->and($user->toArray())->not->toHaveKey('personal_email');

    Notification::assertSentTo($user, AccountPendingApproval::class);
});

test('generated login address is made unique when a name already has an address', function () {
    User::factory()->create(['email' => 'janedoe@codelogic.com']);

    $this->post(route('register'), [
        'name' => 'Jane Doe',
        'personal_email' => 'jane.second@example.test',
        'year_level' => '1st Year',
        'student_id' => '2026-002',
        'password' => 'StrongPassword123!',
        'password_confirmation' => 'StrongPassword123!',
    ])->assertRedirect(route('register.confirmation'))
        ->assertSessionHas('registration_login_address', 'janedoe2@codelogic.com');

    expect(User::where('email', 'janedoe2@codelogic.com')->exists())->toBeTrue();
});

test('public student registration requires a year level and unique student id', function () {
    $this->from(route('register'))
        ->post(route('register'), [
            'name' => 'Jane Doe',
            'personal_email' => 'jane.fields@example.test',
            'password' => 'StrongPassword123!',
            'password_confirmation' => 'StrongPassword123!',
        ])
        ->assertRedirect(route('register'))
        ->assertSessionHasErrors(['year_level', 'student_id']);

    $this->post(route('register'), [
        'name' => 'First Student',
        'personal_email' => 'first.fields@example.test',
        'year_level' => '1st Year',
        'student_id' => 'UNIQUE-001',
        'password' => 'StrongPassword123!',
        'password_confirmation' => 'StrongPassword123!',
    ])->assertRedirect(route('register.confirmation'));

    $this->from(route('register'))
        ->post(route('register'), [
            'name' => 'Second Student',
            'personal_email' => 'second.fields@example.test',
            'year_level' => '2nd Year',
            'student_id' => 'UNIQUE-001',
            'password' => 'StrongPassword123!',
            'password_confirmation' => 'StrongPassword123!',
        ])
        ->assertRedirect(route('register'))
        ->assertSessionHasErrors('student_id');
});

test('successful registration displays a one-time confirmation instead of the login form', function () {
    Notification::fake();

    $response = $this->followingRedirects()->post(route('register'), [
        'name' => 'Jane Doe',
        'personal_email' => 'jane.doe@example.test',
        'year_level' => '2nd Year',
        'student_id' => '2026-003',
        'password' => 'StrongPassword123!',
        'password_confirmation' => 'StrongPassword123!',
    ])->assertOk()
        ->assertSee('Account request received')
        ->assertSee('pending approval')
        ->assertSee('Your Code Logic login address')
        ->assertSee('Check the personal email address you provided')
        ->assertSee('about one day')
        ->assertSee('contact your professor')
        ->assertSee('Back to login')
        ->assertDontSee('Welcome back')
        ->assertDontSee('Remember me')
        ->assertDontSee('Log In')
        ->assertDontSee('Create your account');

    expect(substr_count($response->getContent(), 'janedoe@codelogic.com'))->toBe(1);

    $this->get(route('register.confirmation'))
        ->assertRedirect(route('login'));
});

test('pending and suspended accounts cannot log in or access protected routes', function () {
    $pending = User::factory()->create([
        'account_status' => 'pending',
        'is_whitelisted' => true,
        'password' => 'StrongPassword123!',
    ]);

    $this->post(route('login'), [
        'email' => $pending->email,
        'password' => 'StrongPassword123!',
    ])->assertSessionHasErrors('email');

    $this->actingAs($pending)
        ->get(route('student.dashboard'))
        ->assertForbidden();

    $suspended = User::factory()->create([
        'account_status' => 'suspended',
        'is_whitelisted' => true,
    ]);

    $this->actingAs($suspended)
        ->get(route('profile.edit'))
        ->assertForbidden();
});

test('professors and admins can approve pending accounts and approval sends a notice', function () {
    Notification::fake();
    $professor = User::factory()->create(['role' => 'professor']);
    $student = User::factory()->create([
        'role' => 'student',
        'account_status' => 'pending',
        'personal_email' => 'student.personal@example.test',
        'is_whitelisted' => false,
    ]);
    $classroom = $professor->classrooms()->create([
        'title' => 'BSIT 2A',
        'class_code' => 'BSIT-2026',
    ]);
    $student->enrolledClassrooms()->attach($classroom);

    $this->actingAs($professor)
        ->post(route('users.approve', $student))
        ->assertRedirect();

    $student->refresh();
    expect($student->account_status)->toBe('approved')
        ->and($student->is_whitelisted)->toBeTrue()
        ->and($student->approved_by_user_id)->toBe($professor->id);

    Notification::assertSentTo($student, AccountApproved::class);
});

test('professors cannot approve students until the student is assigned to their class', function () {
    $professor = User::factory()->create(['role' => 'professor']);
    $student = User::factory()->create([
        'role' => 'student',
        'account_status' => 'pending',
        'is_whitelisted' => true,
    ]);

    $this->actingAs($professor)
        ->post(route('users.approve', $student))
        ->assertRedirect()
        ->assertSessionHas('error');

    expect($student->fresh()->account_status)->toBe('pending');
});

test('professors cannot approve non-student accounts or assign roles', function () {
    $professor = User::factory()->create(['role' => 'professor']);
    $pendingAdmin = User::factory()->create([
        'role' => 'admin',
        'account_status' => 'pending',
    ]);

    $this->actingAs($professor)
        ->post(route('users.approve', $pendingAdmin))
        ->assertForbidden();

    $this->actingAs($professor)
        ->patch(route('admin.users.role.update', $pendingAdmin), ['role' => 'student'])
        ->assertForbidden();
});

test('admins can change another user role and status but cannot change their own', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $student = User::factory()->create([
        'role' => 'student',
        'account_status' => 'approved',
        'is_whitelisted' => true,
    ]);

    $this->actingAs($admin)
        ->patch(route('admin.users.role.update', $student), ['role' => 'admin'])
        ->assertRedirect();

    expect($student->fresh()->role)->toBe('admin');

    $this->actingAs($admin)
        ->patch(route('admin.users.status.update', $student), ['status' => 'suspended'])
        ->assertRedirect();

    expect($student->fresh()->account_status)->toBe('suspended');

    $this->actingAs($admin)
        ->patch(route('admin.users.role.update', $admin), ['role' => 'student'])
        ->assertForbidden();

    $this->actingAs($admin)
        ->patch(route('admin.users.status.update', $admin), ['status' => 'suspended'])
        ->assertForbidden();
});

test('professors cannot access student dashboards and pending users cannot use the directory', function () {
    $professor = User::factory()->create(['role' => 'professor']);
    $pending = User::factory()->create([
        'role' => 'student',
        'account_status' => 'pending',
        'is_whitelisted' => true,
    ]);

    $this->actingAs($professor)
        ->get(route('student.dashboard'))
        ->assertForbidden();

    $this->actingAs($pending)
        ->get(route('users.index'))
        ->assertForbidden();
});

test('password reset uses the private address and returns the same response for unknown logins', function () {
    Notification::fake();
    $user = User::factory()->create([
        'email' => 'jane@codelogic.com',
        'personal_email' => 'jane.personal@example.test',
    ]);
    $genericStatus = 'If an account matches that Code Logic login address, a password reset link has been sent to its recovery email.';

    expect($user->routeNotificationForMail())->toBe('jane.personal@example.test');

    $this->from(route('password.request'))
        ->post(route('password.email'), ['email' => $user->email])
        ->assertRedirect(route('password.request'))
        ->assertSessionHas('status', $genericStatus);

    Notification::assertSentTo($user, ResetPassword::class);

    $this->from(route('password.request'))
        ->post(route('password.email'), ['email' => 'nobody@codelogic.com'])
        ->assertRedirect(route('password.request'))
        ->assertSessionHas('status', $genericStatus);
});
