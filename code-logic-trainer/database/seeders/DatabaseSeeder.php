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
        // 1. Create a Default Admin / Test User
        User::firstOrCreate(
            ['email' => 'admin@codelogic.com'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('password123'),
            ]
        );

        // 2. Create a Regular Student / Teammate Test Account
        User::firstOrCreate(
            ['email' => 'student@codelogic.com'],
            [
                'name' => 'Test Student',
                'password' => Hash::make('password123'),
            ]
        );
    }
}
