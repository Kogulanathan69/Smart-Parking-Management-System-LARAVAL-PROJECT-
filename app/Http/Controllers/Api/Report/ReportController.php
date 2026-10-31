<?php

namespace App\Http\Controllers\Api\Report;

use App\Http\Controllers\Controller;
use App\Http\Resources\InspectionResource;
use App\Http\Resources\ParkingSessionResource;
use App\Http\Resources\PaymentResource;
use App\Models\ParkingSession;
use App\Models\Payment;
use App\Models\SafetyInspection;
use App\Models\Vehicle;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Read-only reports for Admin, Super Admin and Auditor. No write endpoints live here.
 */
class ReportController extends Controller
{
    public function __construct(private ReportService $reports) {}

    public function overview(): JsonResponse
    {
        return response()->json(['data' => $this->reports->overview()]);
    }

    public function occupancy(): JsonResponse
    {
        return response()->json(['data' => $this->reports->occupancy()]);
    }

    public function financial(Request $request): JsonResponse
    {
        $this->validateRange($request);

        return response()->json(['data' => $this->reports->financial($request->query('from'), $request->query('to'))]);
    }

    public function staffPerformance(Request $request): JsonResponse
    {
        $this->validateRange($request);

        return response()->json(['data' => $this->reports->staffPerformance($request->query('from'), $request->query('to'))]);
    }

    public function sessions(Request $request): AnonymousResourceCollection
    {
        $this->validateRange($request);
        [$start, $end] = $this->reports->range($request->query('from'), $request->query('to'));

        return ParkingSessionResource::collection(ParkingSession::with(['vehicle.category', 'zone', 'slot', 'package', 'receipt'])
            ->whereBetween('entry_at', [$start, $end])
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('plate'), fn ($q, $p) => $q->whereHas('vehicle', fn ($v) => $v->where('identifier', Vehicle::normalizePlate($p))))
            ->latest('entry_at')->paginate(50));
    }

    public function payments(Request $request): AnonymousResourceCollection
    {
        $this->validateRange($request);
        [$start, $end] = $this->reports->range($request->query('from'), $request->query('to'));

        return PaymentResource::collection(Payment::with('method')
            ->whereBetween('created_at', [$start, $end])
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->latest()->paginate(50));
    }

    public function inspections(Request $request): AnonymousResourceCollection
    {
        $this->validateRange($request);
        [$start, $end] = $this->reports->range($request->query('from'), $request->query('to'));

        return InspectionResource::collection(SafetyInspection::with(['session', 'evidence'])
            ->whereBetween('scheduled_for', [$start, $end])
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('scheduled_for')->paginate(50));
    }

    private function validateRange(Request $request): void
    {
        $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);
    }
}
