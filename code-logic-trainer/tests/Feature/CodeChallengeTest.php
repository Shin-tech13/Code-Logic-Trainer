<?php

use App\Models\ChallengeCompletion;
use App\Models\CodeChallenge;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function makeCodeChallenge(): CodeChallenge
{
    return CodeChallenge::create([
        'slug' => 'add-two-values',
        'title' => 'Add two values',
        'description' => 'Return the sum of two integers.',
        'difficulty' => 'beginner',
        'function_name' => 'addValues',
        'starter_code' => "<?php\nfunction addValues(int \$a, int \$b): int\n{\n    return 0;\n}\n",
        'test_cases' => [
            ['label' => 'Adds two numbers', 'arguments' => [2, 3], 'expected' => 5],
            ['label' => 'Adds a negative number', 'arguments' => [-2, 1], 'expected' => -1],
        ],
        'badge_name' => 'Addition Star',
        'is_active' => true,
    ]);
}

beforeEach(function () {
    config([
        'services.code_runner.url' => 'http://127.0.0.1:8090',
        'services.code_runner.token' => str_repeat('test-token-', 4),
    ]);
});

test('student can browse active challenges', function () {
    $student = User::factory()->create(['is_whitelisted' => true]);
    $challenge = makeCodeChallenge();

    $this->actingAs($student)
        ->get(route('student.challenges.index'))
        ->assertOk()
        ->assertSee($challenge->title)
        ->assertSee($challenge->badge_name);
});

test('professors cannot access the student code runner', function () {
    $professor = User::factory()->create(['role' => 'professor']);
    $challenge = makeCodeChallenge();

    $this->actingAs($professor)
        ->get(route('student.challenges.show', $challenge))
        ->assertForbidden();
});

test('successful challenge runs unlock a badge once and use database tests', function () {
    $student = User::factory()->create(['is_whitelisted' => true]);
    $challenge = makeCodeChallenge();

    Http::fake([
        'http://127.0.0.1:8090/run' => Http::response([
            'passed' => true,
            'results' => [
                ['label' => 'Adds two numbers', 'passed' => true],
                ['label' => 'Adds a negative number', 'passed' => true],
            ],
            'output' => 'All automated tests passed.',
        ]),
    ]);

    $response = $this->actingAs($student)
        ->postJson(route('student.challenges.run', $challenge), [
            'code' => "<?php\nfunction addValues(int \$a, int \$b): int { return \$a + \$b; }",
            'tests' => [['expected' => 999]],
        ])
        ->assertOk()
        ->assertJsonPath('passed', true)
        ->assertJsonPath('badge', 'Addition Star')
        ->assertJsonPath('badge_unlocked', true)
        ->assertJsonPath('results.0.passed', true);

    expect(ChallengeCompletion::where('user_id', $student->id)
        ->where('code_challenge_id', $challenge->id)
        ->exists())->toBeTrue();

    Http::assertSent(fn ($request) => $request->url() === 'http://127.0.0.1:8090/run'
        && $request->hasHeader('Authorization', 'Bearer '.str_repeat('test-token-', 4))
        && $request['tests'][0]['expected'] === 5
        && ! isset($request['tests_from_browser']));

    Http::fake([
        'http://127.0.0.1:8090/run' => Http::response([
            'passed' => true,
            'results' => [
                ['label' => 'Adds two numbers', 'passed' => true],
                ['label' => 'Adds a negative number', 'passed' => true],
            ],
            'output' => 'All automated tests passed.',
        ]),
    ]);

    $this->actingAs($student)
        ->postJson(route('student.challenges.run', $challenge), [
            'code' => "<?php\nfunction addValues(int \$a, int \$b): int { return \$a + \$b; }",
        ])
        ->assertOk()
        ->assertJsonPath('badge_unlocked', false);
});

test('failed challenge runs do not unlock badges', function () {
    $student = User::factory()->create(['is_whitelisted' => true]);
    $challenge = makeCodeChallenge();

    Http::fake([
        'http://127.0.0.1:8090/run' => Http::response([
            'passed' => false,
            'results' => [
                ['label' => 'Adds two numbers', 'passed' => false],
                ['label' => 'Adds a negative number', 'passed' => true],
            ],
            'output' => '1 of 2 tests passed.',
        ]),
    ]);

    $this->actingAs($student)
        ->postJson(route('student.challenges.run', $challenge), ['code' => '<?php'])
        ->assertOk()
        ->assertJsonPath('passed', false)
        ->assertJsonPath('badge', null)
        ->assertJsonPath('results.0.passed', false);

    expect(ChallengeCompletion::count())->toBe(0);
});

test('code size is validated before reaching the runner', function () {
    $student = User::factory()->create(['is_whitelisted' => true]);
    $challenge = makeCodeChallenge();
    Http::fake();

    $this->actingAs($student)
        ->postJson(route('student.challenges.run', $challenge), ['code' => str_repeat('x', 12001)])
        ->assertUnprocessable();

    Http::assertNothingSent();
});
