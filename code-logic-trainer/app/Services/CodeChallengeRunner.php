<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class CodeChallengeRunner
{
    /**
     * Execute untrusted code in the separately hosted, isolated runner.
     *
     * @param array<int, array{label: string, arguments: array<int, mixed>, expected: mixed}> $testCases
     * @return array{passed: bool, results: array<int, array{label: string, passed: bool}>, output: string}
     */
    public function run(string $code, string $functionName, array $testCases): array
    {
        $url = rtrim((string) config('services.code_runner.url'), '/');
        $token = (string) config('services.code_runner.token');

        if ($url === '' || $token === '') {
            throw new RuntimeException('The code execution runner is not configured.');
        }

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout(12)
                ->post($url.'/run', [
                    'code' => $code,
                    'function_name' => $functionName,
                    'tests' => $testCases,
                ]);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('The code execution runner could not be reached.', 0, $exception);
        }

        if (! $response->successful()) {
            throw new RuntimeException('The code execution runner returned an error.');
        }

        $result = $response->json();
        if (! is_array($result)
            || ! is_bool($result['passed'] ?? null)
            || ! is_array($result['results'] ?? null)
            || ! is_string($result['output'] ?? null)) {
            throw new RuntimeException('The code execution runner returned an invalid response.');
        }

        return $result;
    }
}
