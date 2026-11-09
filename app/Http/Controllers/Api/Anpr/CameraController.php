<?php

namespace App\Http\Controllers\Api\Anpr;

use App\Http\Controllers\Controller;
use App\Http\Requests\AnprCameraRequest;
use App\Http\Resources\AnprCameraResource;
use App\Models\AnprCamera;
use App\Services\Anpr\AnprCameraService;
use App\Services\SettingsService;
use App\Support\Anpr\AnprSchema;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CameraController extends Controller
{
    public function __construct(private AnprCameraService $cameras) {}

    public function index(): AnonymousResourceCollection|JsonResponse
    {
        if (! AnprSchema::cameras()) {
            return response()->json(['data' => [], 'meta' => ['configuration_state' => 'not_configured']]);
        }
        return AnprCameraResource::collection(AnprCamera::orderBy('code')->get());
    }

    /**
     * The device token is returned ONCE here (and on rotation); only its hash is stored.
     */
    public function store(AnprCameraRequest $request): JsonResponse
    {
        if (! AnprSchema::cameras()) {
            return response()->json(AnprSchema::unavailable('Camera registration'), 409);
        }
        [$camera, $token] = $this->cameras->create($request->validated(), $request->user());

        return response()->json(['data' => new AnprCameraResource($camera->refresh()), 'device_token' => $token, 'device_token_notice' => 'Store this token in the ANPR agent configuration now; it cannot be shown again.'], 201);
    }

    public function show(AnprCamera $camera): AnprCameraResource
    {
        return new AnprCameraResource($camera);
    }

    public function update(AnprCameraRequest $request, AnprCamera $camera): AnprCameraResource
    {
        return new AnprCameraResource($this->cameras->update($camera, $request->validated(), $request->user()));
    }

    public function rotateToken(Request $request, AnprCamera $camera): JsonResponse
    {
        return response()->json(['device_token' => $this->cameras->rotateToken($camera, $request->user()), 'device_token_notice' => 'The previous token stops working immediately.']);
    }

    public function health(AnprCamera $camera, SettingsService $settings): JsonResponse
    {
        $offlineAfter = (int) $settings->get('anpr.camera_offline_after_seconds');

        return response()->json(['data' => [
            'camera' => new AnprCameraResource($camera),
            'status' => $camera->liveStatus($offlineAfter),
            'recent_logs' => $camera->healthLogs()->latest('id')->limit(50)->get(['component', 'status', 'message', 'latency_ms', 'created_at']),
            'events_last_24h' => $camera->events()->where('captured_at', '>=', now()->subDay())->selectRaw('recognition_status, count(*) as total')->groupBy('recognition_status')->pluck('total', 'recognition_status'),
        ]]);
    }
}
