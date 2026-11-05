<?php

namespace App\Http\Responses;

use App\Http\Resources\UserResource;
use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Fortify\Contracts\RegisterResponse;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse;

/**
 * After login/registration send each role to its own dashboard (JSON clients get the path).
 */
class RoleBasedLoginResponse implements LoginResponse, RegisterResponse, TwoFactorLoginResponse
{
    public function toResponse($request)
    {
        $user = $request->user();
        $path = $user->dashboardPath();

        if ($request->wantsJson()) {
            return response()->json(['user' => new UserResource($user), 'redirect' => $path], $request->is('register') ? 201 : 200);
        }

        return redirect()->intended($path);
    }
}
