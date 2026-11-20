<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureProfessor
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check() || ! Auth::user()->isApproved() || ! Auth::user()->isProfessor()) {
            abort(403, 'Only professors can access this area.');
        }

        return $next($request);
    }
}
