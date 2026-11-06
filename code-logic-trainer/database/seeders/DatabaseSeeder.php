<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $accounts = config('default_accounts', []);

        // Use environment variable if set, otherwise default to password123
        $defaultPassword = Hash::make(env('SEED_DEFAULT_PASSWORD', 'password123'));

        foreach ($accounts as $userData) {
            User::updateOrCreate(
                ['email' => $userData['email']],
                [
                    'name' => $userData['name'] ?? 'Default User',
                    'password' => isset($userData['password'])
                        ? Hash::make($userData['password'])
                        : $defaultPassword,
                    'role' => $userData['role'] ?? 'student',
                ]
            );
        }
    }
}
