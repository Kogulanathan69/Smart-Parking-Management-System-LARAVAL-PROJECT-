<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreVehicleRequest;
use App\Http\Requests\UpdateVehicleRequest;
use App\Http\Requests\VehicleFilterRequest;
use App\Http\Resources\VehicleResource;
use App\Models\Vehicle;
use App\Services\AuditLogger;
use App\Services\VehicleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class VehicleController extends Controller
{
    /**
     * Own vehicles only. Optional filters: q (plate search), vehicle_category_id, fuel_type.
     */
    public function index(VehicleFilterRequest $request, VehicleService $vehicles): AnonymousResourceCollection
    {
        $query = $request->user()->vehicles()->with(['category', 'openSession'])->getQuery();
        $filters = $request->safe()->except('owner_id');

        return VehicleResource::collection($vehicles->applyFilters($query, $filters)->orderBy('identifier')->get());
    }

    public function store(StoreVehicleRequest $request, VehicleService $vehicles): JsonResponse
    {
        $vehicle = $vehicles->registerForCustomer($request->user(), $request->validated());

        return (new VehicleResource($vehicle->load('category')))->response()->setStatusCode(201);
    }

    public function show(Vehicle $vehicle): VehicleResource
    {
        $this->authorize('view', $vehicle);

        return new VehicleResource($vehicle->load(['category', 'openSession']));
    }

    public function update(UpdateVehicleRequest $request, Vehicle $vehicle, VehicleService $vehicles): VehicleResource
    {
        $this->authorize('update', $vehicle);

        return new VehicleResource($vehicles->update($vehicle, $request->validated(), $request->user()));
    }

    /**
     * Unlinks the vehicle from the account; history is kept.
     */
    public function destroy(Vehicle $vehicle, AuditLogger $audit): JsonResponse
    {
        $this->authorize('delete', $vehicle);
        $vehicle->forceFill(['owner_id' => null])->save();
        $audit->log('vehicle.unlinked', $vehicle);

        return response()->json(null, 204);
    }
}
