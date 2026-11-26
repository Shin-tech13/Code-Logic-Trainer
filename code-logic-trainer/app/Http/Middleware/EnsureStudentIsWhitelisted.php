<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureStudentIsWhitelisted
{
    // Only approved, professor-whitelisted students can enter.
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (! $user) {
            return redirect()->route('login');
        }

        if (! $user->isApproved() || ! $user->isStudent()) {
            abort(403, 'This account is not authorized to access the application.');
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
