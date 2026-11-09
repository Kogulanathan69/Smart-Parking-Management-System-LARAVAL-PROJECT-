<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AnprCamera extends Model
{
    public const DIRECTIONS = ['entry', 'exit', 'dual'];

    public const TYPES = ['ip_rtsp', 'ip_http_snapshot', 'usb_local', 'other'];

    protected $fillable = [
        'code', 'name', 'direction', 'gate_code', 'camera_type', 'stream_url_encrypted', 'stream_username_encrypted',
        'stream_password_encrypted', 'stream_host', 'ingest_token_hash', 'token_rotated_at', 'is_enabled',
        'health_status', 'last_heartbeat_at', 'last_capture_at', 'last_success_at', 'last_error', 'last_error_at', 'created_by',
    ];

    /** Secrets never serialise. */
    protected $hidden = ['stream_url_encrypted', 'stream_username_encrypted', 'stream_password_encrypted', 'ingest_token_hash'];

    protected function casts(): array
    {
        return [
            'stream_url_encrypted' => 'encrypted',
            'stream_username_encrypted' => 'encrypted',
            'stream_password_encrypted' => 'encrypted',
            'is_enabled' => 'boolean',
            'token_rotated_at' => 'datetime',
            'last_heartbeat_at' => 'datetime',
            'last_capture_at' => 'datetime',
            'last_success_at' => 'datetime',
            'last_error_at' => 'datetime',
        ];
    }

    public function events(): HasMany
    {
        return $this->hasMany(AnprEvent::class);
    }

    public function healthLogs(): HasMany
    {
        return $this->hasMany(AnprCameraHealthLog::class);
    }

    public function handles(string $direction): bool
    {
        return $this->direction === 'dual' || $this->direction === $direction;
    }

    /**
     * Online = heartbeat (or capture) within the configured window.
     */
    public function liveStatus(int $offlineAfterSeconds): string
    {
        if (! $this->is_enabled) {
            return 'disabled';
        }
        $last = collect([$this->last_heartbeat_at, $this->last_capture_at])->filter()->max();
        if (! $last) {
            return 'unknown';
        }
        if ($last->lt(now()->subSeconds($offlineAfterSeconds))) {
            return 'offline';
        }

        return $this->health_status === 'error' ? 'error' : 'online';
    }
}
