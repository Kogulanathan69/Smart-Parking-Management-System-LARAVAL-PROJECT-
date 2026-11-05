<?php

namespace App\Http\Controllers\Api\Anpr;

use App\Contracts\AnprEngine;
use App\Http\Controllers\Controller;
use App\Http\Resources\AnprCameraResource;
use App\Http\Resources\AnprEventResource;
use App\Models\AnprCamera;
use App\Models\AnprCameraHealthLog;
use App\Models\AnprEvent;
use App\Models\AnprReviewAction;
use App\Services\SettingsService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Read-only ANPR reporting (Admin, Super Admin, Auditor) and health for the LAN dashboard.
 */
class AnprReportController extends Controller
{
    public function overview(Request $request): JsonResponse
    {
        $data = $request->validate(['hours' => ['nullable', 'integer', 'min:1', 'max:720']]);
        $since = now()->subHours((int) ($data['hours'] ?? 24));
        $events = AnprEvent::where('captured_at', '>=', $since);
        $byStatus = (clone $events)->selectRaw('recognition_status, count(*) as total')->groupBy('recognition_status')->pluck('total', 'recognition_status');
        $automatic = (int) $byStatus->except(['manual', 'duplicate'])->sum();
        $recognized = (int) ($byStatus['recognized'] ?? 0);

        return response()->json(['data' => [
            'since' => $since->toIso8601String(),
            'events_by_status' => $byStatus,
            'automatic_events' => $automatic,
            'recognition_rate_percent' => $automatic ? round($recognized * 100 / $automatic, 1) : null,
            'pending_reviews' => AnprEvent::where('verification_status', 'pending')->whereNull('consumed_at')->whereNotIn('recognition_status', ['duplicate'])->count(),
            'corrections' => AnprReviewAction::where('created_at', '>=', $since)->where('action', 'correct')->count(),
            'manual_identifications' => (int) ($byStatus['manual'] ?? 0),
            'avg_processing_ms' => (int) round((float) (clone $events)->whereNotNull('processing_ms')->avg('processing_ms')),
            'max_processing_ms' => (int) (clone $events)->max('processing_ms'),
            'note' => 'Recognition rate is the share of automatic readings above the confidence threshold. It is NOT a measured accuracy figure; use anpr-service/evaluate.py with a labelled dataset for accuracy.',
        ]]);
    }

    public function cameraHealth(SettingsService $settings, AnprEngine $engine): JsonResponse
    {
        $offlineAfter = (int) $settings->get('anpr.camera_offline_after_seconds');
        $engineHealth = $engine->health();

        return response()->json(['data' => [
            'engine' => ['driver' => $engine->name(), 'available' => $engineHealth['available'], 'latency_ms' => $engineHealth['latency_ms']],
            'cameras' => AnprCameraResource::collection(AnprCamera::orderBy('code')->get()),
            'offline' => AnprCamera::where('is_enabled', true)->get()->filter(fn ($c) => in_array($c->liveStatus($offlineAfter), ['offline', 'unknown', 'error'], true))->pluck('code')->values(),
            'recent_logs' => AnprCameraHealthLog::with('camera:id,code')->latest('id')->limit(50)->get()->map(fn ($l) => [
                'camera' => $l->camera?->code, 'component' => $l->component, 'status' => $l->status, 'message' => $l->message, 'latency_ms' => $l->latency_ms, 'at' => $l->created_at?->toIso8601String(),
            ]),
        ]]);
    }

    public function history(Request $request): AnonymousResourceCollection
    {
        $f = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'recognition_status' => ['nullable', 'string', 'max:20'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $tz = config('parking.business_timezone');

        return AnprEventResource::collection(AnprEvent::with('camera')
            ->when($f['from'] ?? null, fn ($q, $d) => $q->where('captured_at', '>=', CarbonImmutable::parse($d, $tz)->startOfDay()->utc()))
            ->when($f['to'] ?? null, fn ($q, $d) => $q->where('captured_at', '<=', CarbonImmutable::parse($d, $tz)->endOfDay()->utc()))
            ->when($f['recognition_status'] ?? null, fn ($q, $s) => $q->where('recognition_status', $s))
            ->latest('captured_at')->paginate($request->integer('per_page', 50))->withQueryString());
    }

    public function corrections(Request $request): JsonResponse
    {
        return response()->json(AnprReviewAction::with(['event:id,uuid,direction,raw_text,normalized_plate', 'user:id,name,role'])
            ->whereIn('action', ['correct', 'reject', 'manual_create'])
            ->latest('id')->paginate(50));
    }

    /**
     * Unauthenticated-safe liveness for the LAN dashboard: no secrets, no internals.
     */
    public function liveness(AnprEngine $engine): JsonResponse
    {
        return response()->json(['status' => 'ok', 'anpr_engine' => $engine->health()['available'] ? 'available' : 'unavailable', 'time' => now()->toIso8601String()]);
    }
}
