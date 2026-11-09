<?php

namespace App\Http\Resources;

use App\Services\SettingsService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Never includes the stream URL, credentials or token hash — only "is configured" flags and the host.
 */
class AnprCameraResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $admin = (bool) $request->user()?->hasAnyRole('admin', 'super_admin');

        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'direction' => $this->direction,
            'gate_code' => $this->gate_code,
            'camera_type' => $this->camera_type,
            'is_enabled' => $this->is_enabled,
            'status' => $this->liveStatus((int) app(SettingsService::class)->get('anpr.camera_offline_after_seconds')),
            'last_heartbeat_at' => $this->last_heartbeat_at?->toIso8601String(),
            'last_capture_at' => $this->last_capture_at?->toIso8601String(),
            'last_success_at' => $this->last_success_at?->toIso8601String(),
            'last_error' => $this->last_error,
            'last_error_at' => $this->last_error_at?->toIso8601String(),
            'stream_host' => $this->when($admin, $this->stream_host),
            'stream_configured' => $this->when($admin, fn () => $this->stream_url_encrypted !== null),
            'credentials_configured' => $this->when($admin, fn () => $this->stream_username_encrypted !== null || $this->stream_password_encrypted !== null),
            'token_rotated_at' => $this->when($admin, fn () => $this->token_rotated_at?->toIso8601String()),
        ];
    }
}
