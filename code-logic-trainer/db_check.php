<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo 'connection: ' . DB::connection()->getDriverName() . PHP_EOL;
echo 'database:   ' . DB::connection()->getDatabaseName() . PHP_EOL;
echo PHP_EOL;

echo '== migrations ==' . PHP_EOL;
foreach (DB::table('migrations')->orderBy('migration')->get() as $m) {
    echo $m->migration . ' | batch ' . $m->batch . PHP_EOL;
}
echo PHP_EOL;

foreach (['users', 'whitelisted_students', 'quizzes', 'quiz_sessions', 'questions', 'session_participants'] as $t) {
    echo '== ' . $t . ' ==' . PHP_EOL;
    try {
        foreach (DB::table($t)->get() as $row) {
            echo json_encode($row) . PHP_EOL;
        }
    } catch (Throwable $e) {
        echo 'ERROR: ' . $e->getMessage() . PHP_EOL;
    }
    echo PHP_EOL;
}
