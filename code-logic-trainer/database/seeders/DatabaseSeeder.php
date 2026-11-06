<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $accounts = config('default_accounts', []);
        $validatedAccounts = [];
        $emails = [];

        foreach ($accounts as $index => $userData) {
            $number = $index + 1;
            foreach (['name', 'email', 'role'] as $field) {
                if (! is_string($userData[$field] ?? null) || trim($userData[$field]) === '') {
                    throw new RuntimeException("Default account {$number} is missing a valid {$field}.");
                }
            }

            $email = strtolower($userData['email']);
            if (
                strlen($userData['name']) > 255
                || strlen($email) > 255
                || filter_var($email, FILTER_VALIDATE_EMAIL) === false
                || ! in_array($userData['role'], ['admin', 'professor', 'student'], true)
                || in_array($email, $emails, true)
            ) {
                throw new RuntimeException("Default account {$number} has invalid or duplicate account details.");
            }

            $emails[] = $email;
            $userData['email'] = $email;
            $validatedAccounts[] = $userData;
        }

        DB::transaction(function () use ($validatedAccounts): void {
            foreach ($validatedAccounts as $userData) {
                $exists = User::where('email', $userData['email'])->exists();
                $attributes = [
                    'name' => $userData['name'],
                    'role' => $userData['role'],
                ];

                if ($userData['role'] === 'student') {
                    $attributes['is_whitelisted'] = true;
                }

                if (! $exists) {
                    $password = $userData['password'] ?? null;
                    if (! is_string($password) || strlen($password) < 12) {
                        throw new RuntimeException(
                            'Set a unique password of at least 12 characters for each new default account in the deployment environment.'
                        );
                    }

                    $attributes['password'] = Hash::make($password);
                }

                User::updateOrCreate(
                    ['email' => $userData['email']],
                    $attributes
                );
            }

            $this->call(CodeChallengeSeeder::class);
        });
    }
}
