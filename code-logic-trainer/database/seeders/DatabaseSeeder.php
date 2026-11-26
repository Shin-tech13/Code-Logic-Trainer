<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = config('default_accounts', []);

        // Let deployments override the default seed password.
        $defaultPassword = Hash::make(env('SEED_DEFAULT_PASSWORD', 'password123'));

        foreach ($accounts as $userData) {
            if (User::where('email', $userData['email'])->exists()) {
                continue;
            }

            $user = new User([
                'name' => $userData['name'] ?? 'Default User',
                'email' => $userData['email'],
                'personal_email' => $userData['personal_email'] ?? null,
                'password' => isset($userData['password'])
                    ? Hash::make($userData['password'])
                    : $defaultPassword,
            ]);
            $user->forceFill([
                'role' => $userData['role'] ?? 'student',
                'account_status' => 'approved',
                'is_whitelisted' => ($userData['role'] ?? 'student') === 'student',
            ]);
            $user->save();
        }
    }
}
