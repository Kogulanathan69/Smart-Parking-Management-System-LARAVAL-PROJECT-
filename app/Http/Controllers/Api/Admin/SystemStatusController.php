<?php

namespace App\Http\Controllers\Api\Admin;

use App\Contracts\AnprEngine;
use App\Http\Controllers\Controller;
use App\Models\AnprCamera;
use App\Models\GateCommand;
use App\Services\SettingsService;
use Illuminate\Http\JsonResponse;

/**
 * Read-only integration status so the UI can label hardware honestly (simulated vs connected).
 * Returns driver NAMES only — never URLs, tokens, credentials or environment values.
 */
class SystemStatusController extends Controller
{
    /** Drivers that only simulate or defer to a person; anything else is treated as a real integration. */
    private const SIMULATED = [
        'gate_controller' => ['log'],
        'payment_gateway' => ['none'],
        'ticket_printer' => ['browser'],
        'plate_recognizer' => ['manual'],
        'anpr_engine' => ['disabled', 'fake'],
    ];

    public function __invoke(AnprEngine $engine, SettingsService $settings): JsonResponse
    {
        $drivers = [];
        foreach (self::SIMULATED as $key => $simulated) {
            $name = (string) config("parking.drivers.$key");
            $drivers[$key] = ['driver' => $name, 'simulated' => in_array($name, $simulated, true)];
        }

        $health = $engine->health();
        $offlineAfter = (int) $settings->get('anpr.camera_offline_after_seconds');
        $cameras = AnprCamera::all();
        $lastGate = GateCommand::latest('id')->first();

        return response()->json(['data' => [
            'environment' => app()->environment(),
            'timezone' => config('parking.business_timezone'),
            'drivers' => $drivers,
            'anpr_engine' => ['driver' => $drivers['anpr_engine']['driver'], 'available' => (bool) ($health['available'] ?? false), 'latency_ms' => $health['latency_ms'] ?? null],
            'cameras' => [
                'total' => $cameras->count(),
                'enabled' => $cameras->where('is_enabled', true)->count(),
                'online' => $cameras->filter(fn ($c) => $c->is_enabled && $c->liveStatus($offlineAfter) === 'online')->count(),
            ],
            'gate' => [
                'release_slot_on' => $settings->get('exit.release_slot_on'),
                'last_command' => $lastGate ? ['status' => $lastGate->status, 'driver' => $lastGate->driver, 'at' => $lastGate->created_at?->toIso8601String()] : null,
                'failed_last_24h' => GateCommand::where('status', 'failed')->where('created_at', '>=', now()->subDay())->count(),
            ],
            'verification_mode' => $settings->get('anpr.verification_mode'),
            'backups' => null, // no backup job exists in this backend; the UI shows "not monitored"
        ]]);
    }
}
