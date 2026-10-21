<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureStudentIsWhitelisted
{
    /**
     * Block students whose email has not been whitelisted by a professor.
     * Professors always pass through.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (! $user) {
            return redirect()->route('login');
        }

        if ($user->role === 'professor') {
            return $next($request);
        }

        if (! $user->is_whitelisted) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors(['email' => 'Your account is not authorized yet. Please contact your professor to be added to the whitelist.']);
        }

        return $next($request);
    }
}
