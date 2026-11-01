<?php

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEntryRequest;
use App\Http\Resources\ParkingSessionResource;
use App\Http\Resources\VehicleResource;
use App\Models\ParkingPackage;
use App\Models\Vehicle;
use App\Models\VehicleCategory;
use App\Models\ZoneType;
use App\Services\EntryService;
use App\Services\QrTicketService;
use App\Services\ReportService;
use App\Services\ReservationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EntryController extends Controller
{
    public function lookupVehicle(Request $request): JsonResponse
    {
        $request->validate(['plate' => ['required', 'string', 'max:30']]);
        $vehicle = Vehicle::with(['category', 'openSession', 'owner'])->where('identifier', Vehicle::normalizePlate($request->query('plate')))->first();

        return response()->json([
            'found' => (bool) $vehicle,
            'vehicle' => $vehicle ? new VehicleResource($vehicle) : null,
            'owner' => $vehicle?->owner ? ['id' => $vehicle->owner->id, 'name' => $vehicle->owner->name, 'phone' => $vehicle->owner->phone] : null,
            'active_session' => $vehicle?->openSession?->reference,
            'active_reservation' => ($reservation = $vehicle ? app(ReservationService::class)->activeFor($vehicle->id) : null)
                ? app(ReservationService::class)->summary($reservation) + ['is_valid' => $reservation->isActiveAt()]
                : null,
        ]);
    }

    /**
     * Zones with free slots for a vehicle category, plus the packages offered in each.
     */
    public function availability(Request $request, ReportService $reports): JsonResponse
    {
        $request->validate(['vehicle_category_id' => ['nullable', 'integer', 'exists:vehicle_categories,id']]);
        $categoryId = $request->integer('vehicle_category_id') ?: null;
        $categoryCode = $categoryId ? VehicleCategory::find($categoryId)->code : null;
        $packages = ParkingPackage::with('zoneTypes')->where('is_active', true)->where('billing_mode', '!=', ParkingPackage::MODE_ADDON)->get();

        $zones = collect($reports->occupancy())->where('is_active', true)->map(function ($zone) use ($categoryCode, $packages) {
            if ($categoryCode) {
                $row = collect($zone['by_vehicle_category'])->firstWhere('vehicle_category', $categoryCode);
                $zone['available_for_category'] = $row['available'] ?? 0;
                $zone['is_full'] = ($row['available'] ?? 0) === 0;
            }
            $typeId = ZoneType::where('code', $zone['zone_type'])->value('id');
            $zone['packages'] = $packages->filter(fn ($p) => $p->allowedInZoneType($typeId))->map->only(['id', 'code', 'name'])->values();

            return $zone;
        })->values();

        return response()->json(['data' => $zones]);
    }

    public function store(StoreEntryRequest $request, EntryService $entries, QrTicketService $tickets): JsonResponse
    {
        [$session, $payload] = $entries->recordEntry($request->user(), $request->validated());
        $session->load(['vehicle.category', 'zone', 'slot', 'package']);

        return response()->json([
            'data' => new ParkingSessionResource($session),
            'ticket' => $tickets->printable($session),
        ], 201);
    }
}
