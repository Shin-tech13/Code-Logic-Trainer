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
        'password' => 'StrongPassword123!',
        'password_confirmation' => 'StrongPassword123!',
        'role' => 'admin',
        'account_status' => 'approved',
        'is_whitelisted' => true,
    ])->assertRedirect(route('login'))
        ->assertSessionHas('code_logic_login', 'janedoe@codelogic.com');

    $user = User::where('email', 'janedoe@codelogic.com')->firstOrFail();

    expect($user->personal_email)->toBe('jane.doe@example.test')
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
        'password' => 'StrongPassword123!',
        'password_confirmation' => 'StrongPassword123!',
    ])->assertRedirect(route('login'))
        ->assertSessionHas('code_logic_login', 'janedoe2@codelogic.com');

    expect(User::where('email', 'janedoe2@codelogic.com')->exists())->toBeTrue();
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

    $this->actingAs($professor)
        ->post(route('users.approve', $student))
        ->assertRedirect();

    $student->refresh();
    expect($student->account_status)->toBe('approved')
        ->and($student->is_whitelisted)->toBeTrue()
        ->and($student->approved_by_user_id)->toBe($professor->id);

    Notification::assertSentTo($student, AccountApproved::class);
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
