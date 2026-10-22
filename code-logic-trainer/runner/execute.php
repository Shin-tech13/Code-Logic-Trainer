<?php

declare(strict_types=1);

const RESULT_MARKER = '__CODE_RUNNER_RESULT__:';

$payload = json_decode(stream_get_contents(STDIN), true);
$caseFile = json_decode((string) file_get_contents('/work/cases.json'), true);
$functionName = $caseFile['function_name'] ?? '';
$arguments = $payload['arguments'] ?? null;
$results = [];

if (! is_string($functionName)
    || ! preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,63}$/', $functionName)
    || ! is_array($arguments)
    || ! is_array($caseFile['arguments'] ?? null)
    || count($arguments) !== count($caseFile['arguments'])) {
    fwrite(STDOUT, RESULT_MARKER.json_encode([]).PHP_EOL);
    exit(0);
}

ob_start();
try {
    require '/work/solution.php';
    $loadOutput = ob_get_clean();
} catch (Throwable) {
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    fwrite(STDOUT, RESULT_MARKER.json_encode(
        array_fill(0, count($arguments), ['ok' => false])
    ).PHP_EOL);
    exit(0);
}
$loadOutput = ob_get_level() > 0 ? ob_get_clean() : '';

foreach ($arguments as $testArguments) {
    if (! is_array($testArguments)) {
        $results[] = ['ok' => false];
        continue;
    }

    ob_start();
    try {
        if (! function_exists($functionName)) {
            throw new RuntimeException('Required function is missing.');
        }

        $value = call_user_func_array($functionName, $testArguments);
        $testOutput = ob_get_clean();
        $encoded = json_encode($value, JSON_THROW_ON_ERROR);
        $results[] = [
            'ok' => true,
            'value' => json_decode($encoded, true, 512, JSON_THROW_ON_ERROR),
            'output' => trim($testOutput),
        ];
    } catch (Throwable) {
        $testOutput = ob_get_level() > 0 ? ob_get_clean() : '';
        $results[] = ['ok' => false, 'output' => trim($testOutput)];
    }
}

fwrite(STDOUT, RESULT_MARKER.json_encode([
    'results' => $results,
    'output' => trim($loadOutput),
], JSON_THROW_ON_ERROR).PHP_EOL);
