<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Logs out an authenticated user whose account became inactive or suspended,
 * so suspension takes effect on the very next request.
 */
class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->isActive()) {
            Auth::guard('web')->logout();
            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => 'Your account is not active.', 'error_code' => 'account_inactive'], 403);
            }

            return redirect('/login')->withErrors(['email' => 'Your account is not active.']);
        }

        return $next($request);
    }
}
