<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Create a Default Professor Account
        $professor = User::firstOrCreate(
            ['email' => 'professor@codelogic.com'],
            [
                'name' => 'Professor User',
                'password' => Hash::make('password123'),
                'role' => 'professor',
            ]
        );

        // 2. Create a Default Admin / Test User
        User::firstOrCreate(
            ['email' => 'admin@codelogic.com'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('password123'),
            ]
        );

        // 3. Create a Regular Student / Teammate Test Account
        $student = User::firstOrCreate(
            ['email' => 'student@codelogic.com'],
            [
                'name' => 'Test Student',
                'password' => Hash::make('password123'),
                'is_whitelisted' => true,
            ]
        );

        // 4. Whitelist the test student under the professor
        \App\Models\WhitelistedStudent::firstOrCreate(
            ['email' => 'student@codelogic.com'],
            [
                'professor_id' => $professor->id,
                'name' => 'Test Student',
                'status' => 'active',
            ]
        );
    }
}
