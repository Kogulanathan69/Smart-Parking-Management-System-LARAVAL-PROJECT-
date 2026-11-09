<?php

namespace App\Http\Middleware;

use App\Services\Anpr\AnprCameraService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Machine authentication for on-site camera agents. Completely separate from human sessions:
 * header "X-Anpr-Camera: <code>" + "Authorization: Bearer <device token>" (stored as SHA-256 only).
 */
class AuthenticateAnprDevice
{
    public function __construct(private AnprCameraService $cameras) {}

    public function handle(Request $request, Closure $next): Response
    {
        $camera = $this->cameras->authenticate($request->header('X-Anpr-Camera'), $request->bearerToken());
        if (! $camera) {
            return response()->json(['message' => 'Unauthenticated device.', 'error_code' => 'device_unauthenticated'], 401);
        }
        if (! $camera->is_enabled) {
            return response()->json(['message' => 'This camera is disabled.', 'error_code' => 'camera_disabled'], 403);
        }
        $request->attributes->set('anpr_camera', $camera);

        return $next($request);
    }
}
