<?php

namespace App\Http\Controllers\Api\Report;

use App\Http\Controllers\Controller;
use App\Http\Requests\SessionFilterRequest;
use App\Http\Resources\InspectionResource;
use App\Http\Resources\ParkingSessionResource;
use App\Http\Resources\PaymentResource;
use App\Models\ParkingSession;
use App\Models\Payment;
use App\Models\SafetyInspection;
use App\Services\ReportService;
use App\Support\SessionFilters;
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

    /**
     * Parking records with all filters combinable (see SessionFilters). Without any date filter the
     * previous default (last 7 days by entry) still applies, so existing clients keep working.
     */
    public function sessions(SessionFilterRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();
        if (! SessionFilters::range($filters)) {
            [$start, $end] = $this->reports->range(null, null);
            $filters['from'] = $start->setTimezone(config('parking.business_timezone'))->toDateString();
            $filters['to'] = $end->setTimezone(config('parking.business_timezone'))->toDateString();
        }

        $query = SessionFilters::apply(ParkingSession::query()->with(['vehicle.category', 'vehicleCategory', 'zone', 'slot', 'package', 'receipt']), $filters);

        return ParkingSessionResource::collection($query->latest('entry_at')->orderByDesc('id')->paginate($request->integer('per_page', 50))->withQueryString());
    }

    /**
     * Calendar view: one row per business-local day with entries, exits, sessions parked at day end,
     * revenue received and penalties applied. Maximum 62 days per request.
     */
    public function calendar(Request $request): JsonResponse
    {
        $data = $request->validate([
            'month' => ['nullable', 'date_format:Y-m', 'prohibits:from,to'],
            'from' => ['nullable', 'required_with:to', 'date_format:Y-m-d'],
            'to' => ['nullable', 'required_with:from', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        return response()->json(['data' => $this->reports->calendar($data['month'] ?? null, $data['from'] ?? null, $data['to'] ?? null)]);
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
