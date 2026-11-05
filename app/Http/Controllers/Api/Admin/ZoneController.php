<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SetCapacityRequest;
use App\Http\Requests\StoreZoneRequest;
use App\Http\Requests\UpdateSlotStatusRequest;
use App\Http\Requests\UpdateZoneRequest;
use App\Http\Resources\SlotResource;
use App\Http\Resources\ZoneResource;
use App\Models\ParkingSlot;
use App\Models\ParkingZone;
use App\Models\VehicleCategory;
use App\Services\AuditLogger;
use App\Services\ZoneService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class ZoneController extends Controller
{
    public function __construct(private ZoneService $zones, private AuditLogger $audit) {}

    public function index(): AnonymousResourceCollection
    {
        $occupancy = $this->zones->occupancyFor();

        return ZoneResource::collection(ParkingZone::with(['zoneType', 'vehicleCategories'])->orderBy('code')->get()
            ->each(fn ($zone) => $zone->occupancy = $occupancy[$zone->id] ?? []));
    }

    public function store(StoreZoneRequest $request): JsonResponse
    {
        $zone = DB::transaction(function () use ($request) {
            $zone = ParkingZone::create($request->safe()->except('vehicle_category_ids'));
            $zone->vehicleCategories()->sync($request->validated('vehicle_category_ids'));
            $this->audit->log('zone.created', $zone, [], $zone->toArray());

            return $zone;
        });

        return (new ZoneResource($zone->load(['zoneType', 'vehicleCategories'])))->response()->setStatusCode(201);
    }

    public function show(ParkingZone $zone): ZoneResource
    {
        $zone->occupancy = $this->zones->occupancy($zone);

        return new ZoneResource($zone->load(['zoneType', 'vehicleCategories']));
    }

    public function update(UpdateZoneRequest $request, ParkingZone $zone): ZoneResource
    {
        DB::transaction(function () use ($request, $zone) {
            $original = $zone->getAttributes();
            $zone->update($request->safe()->except('vehicle_category_ids'));
            if ($request->has('vehicle_category_ids')) {
                $removed = $zone->vehicleCategories()->pluck('vehicle_categories.id')->diff($request->validated('vehicle_category_ids'));
                $hasSlots = ParkingSlot::where('parking_zone_id', $zone->id)->whereIn('vehicle_category_id', $removed)->whereNull('decommissioned_at')->exists();
                abort_if($hasSlots, 422, 'Set the capacity of a vehicle category to 0 before removing it from the zone.');
                $zone->vehicleCategories()->sync($request->validated('vehicle_category_ids'));
            }
            $this->audit->logChanges('zone.updated', $zone, $original);
        });

        return $this->show($zone->refresh());
    }

    public function setCapacity(SetCapacityRequest $request, ParkingZone $zone): JsonResponse
    {
        $result = $this->zones->setCapacity($zone, VehicleCategory::findOrFail($request->validated('vehicle_category_id')), $request->integer('capacity'));

        return response()->json(['data' => $result]);
    }

    public function slots(Request $request, ParkingZone $zone): AnonymousResourceCollection
    {
        return SlotResource::collection($zone->slots()
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when(! $request->boolean('include_decommissioned'), fn ($q) => $q->whereNull('decommissioned_at'))
            ->orderBy('code')->get());
    }

    public function updateSlotStatus(UpdateSlotStatusRequest $request, ParkingSlot $slot): SlotResource
    {
        return new SlotResource($this->zones->setSlotStatus($slot, $request->validated('status'), $request->validated('notes')));
    }
}
