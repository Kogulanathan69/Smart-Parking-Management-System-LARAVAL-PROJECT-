<?php

namespace App\Services\Anpr;

use App\Exceptions\ParkingException;
use App\Models\AnprCamera;
use App\Models\AnprCameraHealthLog;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\Anpr\AnprSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AnprCameraService
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * @return array{0: AnprCamera, 1: string} camera and the one-time plaintext device token
     */
    public function create(array $data, User $by): array
    {
        $token = $this->newToken();
        $camera = new AnprCamera;
        $camera->forceFill($this->attributes($data) + [
            'code' => $data['code'],
            'ingest_token_hash' => hash('sha256', $token),
            'token_rotated_at' => now(),
            'created_by' => $by->id,
        ])->save();
        $camera->refresh(); // pick up DB defaults (is_enabled, health_status)
        $this->audit->log('anpr.camera_created', $camera, [], $this->safeSummary($camera));

        return [$camera, $token];
    }

    public function update(AnprCamera $camera, array $data, User $by): AnprCamera
    {
        $original = $this->safeSummary($camera);
        $camera->forceFill($this->attributes($data, $camera))->save();
        // Credentials are deliberately excluded from the audit trail: only "changed" flags are logged.
        $this->audit->log('anpr.camera_updated', $camera, $original, $this->safeSummary($camera), [
            'stream_changed' => array_key_exists('stream_url', $data) || array_key_exists('stream_username', $data) || array_key_exists('stream_password', $data),
        ]);

        return $camera->refresh();
    }

    /**
     * @return string the new plaintext token (shown once)
     */
    public function rotateToken(AnprCamera $camera, User $by): string
    {
        $token = $this->newToken();
        $camera->forceFill(['ingest_token_hash' => hash('sha256', $token), 'token_rotated_at' => now()])->save();
        $this->audit->log('anpr.camera_token_rotated', $camera);

        return $token;
    }

    public function authenticate(?string $code, ?string $token): ?AnprCamera
    {
        if (! $code || ! $token || strlen($token) < 32) {
            return null;
        }
        $camera = AnprCamera::where('code', $code)->first();

        return $camera && hash_equals($camera->ingest_token_hash, hash('sha256', $token)) ? $camera : null;
    }

    /**
     * Configuration for the authenticated on-site ANPR agent, including the decrypted stream credentials.
     */
    public function agentConfig(AnprCamera $camera, array $settings): array
    {
        $this->audit->log('anpr.device_config_read', $camera);

        return [
            'camera' => ['code' => $camera->code, 'direction' => $camera->direction, 'gate_code' => $camera->gate_code, 'camera_type' => $camera->camera_type],
            'stream' => ['url' => $camera->stream_url_encrypted, 'username' => $camera->stream_username_encrypted, 'password' => $camera->stream_password_encrypted],
            'recognition' => [
                'min_confidence' => $settings['anpr.min_confidence_percent'] / 100,
                'timeout_ms' => $settings['anpr.recognition_timeout_ms'],
                'retry_attempts' => $settings['anpr.camera_retry_attempts'],
                'heartbeat_interval_seconds' => $settings['anpr.health_check_interval_seconds'],
                'send_full_frame' => (bool) $settings['anpr.store_full_frame'],
            ],
        ];
    }

    /**
     * Record a heartbeat; health logs are written only on state transitions (keeps the table small).
     */
    public function heartbeat(AnprCamera $camera, string $status, ?string $message, ?int $latencyMs): AnprCamera
    {
        return DB::transaction(function () use ($camera, $status, $message, $latencyMs) {
            $locked = AnprCamera::whereKey($camera->id)->lockForUpdate()->firstOrFail();
            $previous = $locked->health_status;
            $new = $status === 'ok' ? 'online' : 'error';
            $locked->forceFill(array_filter([
                'last_heartbeat_at' => now(),
                'health_status' => $new,
                'last_success_at' => $new === 'online' ? now() : null,
                'last_error' => $new === 'error' ? $this->sanitize($message) : null,
                'last_error_at' => $new === 'error' ? now() : null,
            ], fn ($v) => $v !== null))->save();

            if ($previous !== $new) {
                $this->logHealth($locked, 'camera', $new === 'online' && in_array($previous, ['offline', 'error'], true) ? 'recovered' : $new, $message, $latencyMs);
            }

            return $locked;
        });
    }

    public function markOfflineIfStale(AnprCamera $camera, int $offlineAfterSeconds): bool
    {
        if ($camera->liveStatus($offlineAfterSeconds) !== 'offline' || $camera->health_status === 'offline') {
            return false;
        }
        $camera->forceFill(['health_status' => 'offline'])->save();
        $this->logHealth($camera, 'camera', 'offline', 'No heartbeat or capture within '.$offlineAfterSeconds.'s', null);

        return true;
    }

    public function logHealth(?AnprCamera $camera, string $component, string $status, ?string $message, ?int $latencyMs): void
    {
        // A provider outage must remain a recognition result, not become a database error
        // during a staged installation where health history is not available yet.
        if (! AnprSchema::healthLogs()) {
            return;
        }

        AnprCameraHealthLog::create([
            'anpr_camera_id' => $camera?->id,
            'component' => $component,
            'status' => $status,
            'message' => $this->sanitize($message),
            'latency_ms' => $latencyMs,
        ]);
    }

    /**
     * SSRF / credential-leak guard for camera stream URLs. Laravel never fetches these URLs itself;
     * they are handed only to the authenticated on-site agent, and must point at the camera LAN.
     */
    public function validateStreamUrl(string $url): string
    {
        $parts = parse_url($url);
        if (! $parts || empty($parts['scheme']) || empty($parts['host'])) {
            throw new ParkingException('Invalid camera stream URL.', 'invalid_stream_url');
        }
        if (! in_array(strtolower($parts['scheme']), ['rtsp', 'rtsps', 'http', 'https'], true)) {
            throw new ParkingException('Camera streams must use rtsp, rtsps, http or https.', 'invalid_stream_url');
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new ParkingException('Do not embed credentials in the URL; use the username/password fields.', 'credentials_in_url');
        }
        $host = trim($parts['host'], '[]');

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            if (! filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                throw new ParkingException('Only IPv4 camera addresses are supported.', 'invalid_stream_url');
            }
            if ($this->inCidr($host, '127.0.0.0/8') || $this->inCidr($host, '169.254.0.0/16') || $this->inCidr($host, '0.0.0.0/8')) {
                throw new ParkingException('Loopback and link-local addresses are not allowed for cameras.', 'stream_host_not_allowed');
            }
            foreach (config('parking.anpr.allowed_camera_networks') as $cidr) {
                if ($this->inCidr($host, trim($cidr))) {
                    return $host;
                }
            }
            throw new ParkingException('The camera address is outside the allowed camera networks.', 'stream_host_not_allowed');
        }

        $hostname = strtolower($host);
        if (in_array($hostname, array_map('strtolower', config('parking.anpr.allowed_camera_hostnames')), true)) {
            return $hostname;
        }
        throw new ParkingException('Camera hostnames must be listed in PARKING_ANPR_CAMERA_HOSTNAMES (or use an IP in the camera network).', 'stream_host_not_allowed');
    }

    public function safeSummary(AnprCamera $camera): array
    {
        return $camera->only(['code', 'name', 'direction', 'gate_code', 'camera_type', 'stream_host', 'is_enabled']);
    }

    private function attributes(array $data, ?AnprCamera $existing = null): array
    {
        $attrs = array_intersect_key($data, array_flip(['name', 'direction', 'gate_code', 'camera_type', 'is_enabled']));
        if (array_key_exists('stream_url', $data)) {
            if ($data['stream_url'] === null || $data['stream_url'] === '') {
                $attrs['stream_url_encrypted'] = null;
                $attrs['stream_host'] = null;
            } else {
                $attrs['stream_host'] = $this->validateStreamUrl($data['stream_url']);
                $attrs['stream_url_encrypted'] = $data['stream_url'];
            }
        }
        if (array_key_exists('stream_username', $data)) {
            $attrs['stream_username_encrypted'] = $data['stream_username'] ?: null;
        }
        if (array_key_exists('stream_password', $data)) {
            $attrs['stream_password_encrypted'] = $data['stream_password'] ?: null;
        }
        if (($attrs['camera_type'] ?? $existing?->camera_type) === 'usb_local') {
            // USB cameras are read directly by the agent on the server; no network URL is kept.
            $attrs['stream_url_encrypted'] = null;
            $attrs['stream_host'] = null;
        }

        return $attrs;
    }

    private function newToken(): string
    {
        return 'anpr_'.Str::random(48);
    }

    private function inCidr(string $ip, string $cidr): bool
    {
        if (! str_contains($cidr, '/')) {
            return false;
        }
        [$subnet, $bits] = explode('/', $cidr, 2);
        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);
        if ($ipLong === false || $subnetLong === false) {
            return false;
        }
        $mask = $bits == 0 ? 0 : (~0 << (32 - (int) $bits)) & 0xFFFFFFFF;

        return ($ipLong & $mask) === ($subnetLong & $mask);
    }

    /**
     * Strip anything that looks like a URL/credential from device messages before storing them.
     */
    private function sanitize(?string $message): ?string
    {
        if ($message === null) {
            return null;
        }
        $message = preg_replace('#[a-z]+://\S+#i', '[url]', $message);
        $message = preg_replace('/(pass(word)?|token|secret)\s*[=:]\s*\S+/i', '$1=[redacted]', (string) $message);

        return mb_substr(strip_tags((string) $message), 0, 255);
    }
}
