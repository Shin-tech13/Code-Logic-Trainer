<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class InitialAdminSeeder extends Seeder
{
    public function run(): void
    {
        $emails = [];

        foreach (config('initial_admins.accounts', []) as $index => $account) {
            $number = $index + 1;
            foreach (['name', 'email', 'password'] as $field) {
                if (! is_string($account[$field] ?? null) || trim($account[$field]) === '') {
                    throw new RuntimeException(
                        "Set INITIAL_ADMIN_{$number}_NAME, INITIAL_ADMIN_{$number}_EMAIL, and INITIAL_ADMIN_{$number}_PASSWORD before seeding."
                    );
                }
            }

            if (strlen($account['name']) > 255
                || strlen($account['email']) > 255
                || filter_var($account['email'], FILTER_VALIDATE_EMAIL) === false) {
                throw new RuntimeException("INITIAL_ADMIN_{$number}_NAME or INITIAL_ADMIN_{$number}_EMAIL is invalid.");
            }

            if (strlen($account['password']) < 12) {
                throw new RuntimeException("INITIAL_ADMIN_{$number}_PASSWORD must be at least 12 characters.");
            }

            $email = strtolower($account['email']);
            if (in_array($email, $emails, true)) {
                throw new RuntimeException('Each initial administrator must have a unique email address.');
            }
            $emails[] = $email;

            $admin = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $account['name'],
                    'password' => Hash::make($account['password']),
                    'role' => 'admin',
                    'is_whitelisted' => false,
                ]
            );

            if (! $admin->isAdmin()) {
                throw new RuntimeException(
                    "The configured initial admin email {$admin->email} already belongs to a non-admin account."
                );
            }
        }
    }
}
