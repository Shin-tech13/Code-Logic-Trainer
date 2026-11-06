<?php

return [
    [
        'name' => 'Admin One',
        'email' => 'admin1@codelogic.com',
        'role' => 'admin',
        'password' => env('DEFAULT_ADMIN_1_PASSWORD'),
    ],
    [
        'name' => 'Admin Two',
        'email' => 'admin2@codelogic.com',
        'role' => 'admin',
        'password' => env('DEFAULT_ADMIN_2_PASSWORD'),
    ],
    [
        'name' => 'Student User',
        'email' => 'student@codelogic.com',
        'role' => 'student',
        'password' => env('DEFAULT_STUDENT_PASSWORD'),
    ],
    [
        'name' => 'Professor User',
        'email' => 'professor@codelogic.com',
        'role' => 'professor',
        'password' => env('DEFAULT_PROFESSOR_PASSWORD'),
    ],
];
