<?php

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Controller;
use App\Http\Resources\ParkingSessionResource;
use App\Http\Resources\ReservationResource;
use App\Models\ParkingReservation;
use App\Models\Vehicle;
use App\Services\ReservationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * Guaranteed (weekly/monthly) parking reservations.
 * Staff/Admin/Super Admin: list, detail, renew. Admin/Super Admin: cancel. Customers: own only.
 */
class ReservationController extends Controller
{
    private const RELATIONS = ['vehicle', 'zone', 'slot', 'package', 'periods.billedSession', 'sessions'];

    public function __construct(private ReservationService $reservations) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(['active', 'expired', 'cancelled'])],
            'plate' => ['nullable', 'string', 'max:30'],
            'zone_id' => ['nullable', 'integer'],
            'expiring_within' => ['nullable', 'integer', 'min:1', 'max:43200'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return ReservationResource::collection($this->query($filters)->paginate($request->integer('per_page', 25))->withQueryString());
    }

    public function show(ParkingReservation $reservation): JsonResponse
    {
        $this->authorize('view', $reservation);
        $reservation->load(self::RELATIONS);

        return response()->json([
            'data' => new ReservationResource($reservation),
            'visits' => ParkingSessionResource::collection($reservation->sessions()->with(['zone', 'slot', 'package', 'receipt'])->latest('entry_at')->limit(50)->get()),
        ]);
    }

    public function renew(Request $request, ParkingReservation $reservation): JsonResponse
    {
        $period = $this->reservations->renew($reservation, $request->user());

        return response()->json([
            'data' => new ReservationResource($reservation->refresh()->load(self::RELATIONS)),
            'renewal' => ['sequence' => $period->sequence, 'price_minor' => $period->price_minor, 'status' => $period->status, 'billed_on' => $period->status === 'billed' ? 'current visit' : 'next visit'],
        ], 201);
    }

    public function cancel(Request $request, ParkingReservation $reservation): ReservationResource
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:500'],
            'refund_minor' => ['nullable', 'integer', 'min:1', 'max:100000000000'],
        ]);

        return new ReservationResource($this->reservations->cancel($reservation, $request->user(), $data['reason'], (int) ($data['refund_minor'] ?? 0))->load(self::RELATIONS));
    }

    /* ---------- customer ---------- */

    public function mine(Request $request): AnonymousResourceCollection
    {
        return ReservationResource::collection(
            ParkingReservation::with(self::RELATIONS)->where('customer_id', $request->user()->id)->latest('starts_at')->paginate(20)
        );
    }

    private function query(array $filters)
    {
        return ParkingReservation::query()->with(self::RELATIONS)
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($filters['zone_id'] ?? null, fn ($q, $z) => $q->where('parking_zone_id', $z))
            ->when($filters['plate'] ?? null, fn ($q, $p) => $q->whereIn('vehicle_id', Vehicle::withTrashed()->select('id')->where('identifier', 'like', '%'.Vehicle::normalizePlate($p).'%')))
            ->when($filters['expiring_within'] ?? null, fn ($q, $m) => $q->where('status', 'active')->where('expires_at', '<=', now()->addMinutes((int) $m)))
            ->orderBy('expires_at');
    }
}
