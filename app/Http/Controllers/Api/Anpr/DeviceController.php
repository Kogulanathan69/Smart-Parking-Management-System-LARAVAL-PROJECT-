<?php

namespace App\Http\Controllers\Api\Anpr;

use App\Http\Controllers\Controller;
use App\Http\Requests\AnprDeviceEventRequest;
use App\Services\Anpr\AnprCameraService;
use App\Services\Anpr\AnprEventService;
use App\Services\SettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoints for authenticated on-site camera agents only (see AuthenticateAnprDevice).
 * Ingesting an event creates a record for staff review — it never starts an entry/exit or opens a gate.
 */
class DeviceController extends Controller
{
    public function events(AnprDeviceEventRequest $request, AnprEventService $events): JsonResponse
    {
        [$event, $replayed] = $events->ingestFromDevice($request->attributes->get('anpr_camera'), $request->validated(), $request->file('image'), $request->file('plate_crop'));

        return response()->json([
            'event_uuid' => $event->uuid,
            'recognition_status' => $event->recognition_status,
            'normalized_plate' => $event->normalized_plate,
            'replayed' => $replayed,
        ], $replayed ? 200 : 201);
    }

    public function heartbeat(Request $request, AnprCameraService $cameras): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:ok,error'],
            'message' => ['nullable', 'string', 'max:500'],
            'latency_ms' => ['nullable', 'integer', 'min:0', 'max:600000'],
        ]);
        $camera = $cameras->heartbeat($request->attributes->get('anpr_camera'), $data['status'], $data['message'] ?? null, $data['latency_ms'] ?? null);

        return response()->json(['status' => $camera->health_status, 'server_time' => now()->toIso8601String()]);
    }

    public function config(Request $request, AnprCameraService $cameras, SettingsService $settings): JsonResponse
    {
        return response()->json(['data' => $cameras->agentConfig($request->attributes->get('anpr_camera'), $settings->all())]);
    }
}
