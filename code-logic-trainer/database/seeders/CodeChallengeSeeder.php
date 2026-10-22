<?php

namespace Database\Seeders;

use App\Models\CodeChallenge;
use Illuminate\Database\Seeder;

class CodeChallengeSeeder extends Seeder
{
    public function run(): void
    {
        CodeChallenge::updateOrCreate(
            ['slug' => 'sum-array'],
            [
                'title' => 'Sum an Array',
                'description' => 'Write sumArray to return the total of all integers in the input array. Empty arrays should return 0.',
                'difficulty' => 'beginner',
                'function_name' => 'sumArray',
                'starter_code' => <<<'PHP'
<?php

function sumArray(array $numbers): int
{
    // Return the sum of all numbers.
    return 0;
}
PHP,
                'test_cases' => [
                    ['label' => 'Adds positive values', 'arguments' => [[1, 2, 3]], 'expected' => 6],
                    ['label' => 'Supports negative values', 'arguments' => [[-2, 5, -1]], 'expected' => 2],
                    ['label' => 'Handles an empty array', 'arguments' => [[]], 'expected' => 0],
                ],
                'badge_name' => 'Array Adder',
                'is_active' => true,
            ]
        );

        CodeChallenge::updateOrCreate(
            ['slug' => 'is-even'],
            [
                'title' => 'Even or Odd',
                'description' => 'Write isEven to return true when the given integer is even and false otherwise.',
                'difficulty' => 'beginner',
                'function_name' => 'isEven',
                'starter_code' => <<<'PHP'
<?php

function isEven(int $number): bool
{
    // Return whether the number is even.
    return false;
}
PHP,
                'test_cases' => [
                    ['label' => 'Recognizes a positive even number', 'arguments' => [8], 'expected' => true],
                    ['label' => 'Recognizes an odd number', 'arguments' => [7], 'expected' => false],
                    ['label' => 'Handles negative even numbers', 'arguments' => [-4], 'expected' => true],
                ],
                'badge_name' => 'Even Logic',
                'is_active' => true,
            ]
        );
    }
}
