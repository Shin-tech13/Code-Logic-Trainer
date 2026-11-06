<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'professor' => \App\Http\Middleware\EnsureProfessor::class,
            'student' => \App\Http\Middleware\EnsureStudent::class,
            'whitelisted' => \App\Http\Middleware\EnsureStudentIsWhitelisted::class,
            'admin' => \App\Http\Middleware\EnsureAdmin::class,
            'user-directory' => \App\Http\Middleware\EnsureUserDirectoryAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
