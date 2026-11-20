<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountApproved
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isApproved() || ! in_array($user->role, ['admin', 'professor', 'student'], true)) {
            abort(403, 'This account is not authorized to access the application.');
        }

        return $next($request);
    }
}
