<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserDirectoryAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (! $user || (! $user->isAdmin() && ! $user->isProfessor())) {
            abort(403, 'Only administrators and professors can view the user directory.');
        }

        return $next($request);
    }
}
