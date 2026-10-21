<?php

$db = new PDO('sqlite:codelogic_trainer');
$tables = ['users', 'classrooms', 'classroom_student', 'uploaded_files', 'quiz_sessions', 'questions'];
foreach ($tables as $t) {
    echo "== {$t} ==\n";
    $rows = $db->query("SELECT * FROM {$t}");
    foreach ($rows as $row) {
        echo json_encode($row) . "\n";
    }
    echo "\n";
}
