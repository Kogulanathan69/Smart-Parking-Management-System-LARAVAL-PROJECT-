<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    public function handle(
        Request $request,
        Closure $next,
        string ...$roles
    ): Response {
        $user = $request->user();

        // User must be authenticated.
        if (!$user) {
            abort(401, 'Authentication required.');
        }

        // Block inactive or suspended accounts.
        if (!$user->isActive()) {
            abort(403, 'Your account is not active.');
        }

        // At least one allowed role must be configured.
        if (empty($roles)) {
            abort(403, 'Access denied.');
        }

        // Check whether the user's role is allowed.
        if (!in_array($user->role, $roles, true)) {
            abort(403, 'You do not have permission to access this resource.');
        }

        return $next($request);
    }
}
