<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('sign in and registration both require agreement acceptance', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
        'password' => 'StrongPassword123!',
    ]);

    $this->from(route('login'))
        ->post(route('login'), [
            'email' => $admin->email,
            'password' => 'StrongPassword123!',
        ])
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('terms')
        ->assertSessionHas('errors', fn ($errors) => $errors->first('terms') === 'You must agree to the User Agreement before continuing.');

    expect(auth()->check())->toBeFalse();

    $this->from(route('register'))
        ->post(route('register'), [
            'name' => 'Agreement Test',
            'personal_email' => 'agreement@example.test',
            'year_level' => '1st Year',
            'student_id' => 'AGREE-001',
            'password' => 'StrongPassword123!',
            'password_confirmation' => 'StrongPassword123!',
        ])
        ->assertRedirect(route('register'))
        ->assertSessionHasErrors('terms')
        ->assertSessionHas('errors', fn ($errors) => $errors->first('terms') === 'You must agree to the User Agreement before continuing.');

    expect(User::where('personal_email', 'agreement@example.test')->exists())->toBeFalse();
});

test('auth forms show the policy and preserve agreement input after validation errors', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('name="terms"', false)
        ->assertSee('required', false)
        ->assertSee('Read the User Agreement & Academic Integrity Policy')
        ->assertSee('Tab switches, minimizing the browser, or losing window focus may be recorded');

    $this->get(route('register'))
        ->assertOk()
        ->assertSee('name="terms"', false)
        ->assertSee('required', false)
        ->assertSee('Read the User Agreement & Academic Integrity Policy');

    $this->from(route('register'))
        ->post(route('register'), [
            'name' => 'Agreement Test',
            'personal_email' => 'agreement@example.test',
            'student_id' => 'AGREE-002',
            'password' => 'StrongPassword123!',
            'password_confirmation' => 'StrongPassword123!',
            'terms' => '1',
        ])
        ->assertRedirect(route('register'))
        ->assertSessionHasErrors('year_level');

    $this->get(route('register'))->assertSee('checked', false);
});
