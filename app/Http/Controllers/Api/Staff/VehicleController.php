<?php

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminUpdateVehicleRequest;
use App\Http\Requests\VehicleFilterRequest;
use App\Http\Resources\ParkingSessionResource;
use App\Http\Resources\VehicleResource;
use App\Models\Vehicle;
use App\Services\VehicleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Internal vehicle register (staff read; admin update).
 */
class VehicleController extends Controller
{
    public function __construct(private VehicleService $vehicles) {}

    public function index(VehicleFilterRequest $request): AnonymousResourceCollection
    {
        $query = Vehicle::query()->with(['category', 'openSession']);

        return VehicleResource::collection(
            $this->vehicles->applyFilters($query, $request->validated())->orderBy('identifier')->paginate($request->integer('per_page', 25))
        );
    }

    public function show(Vehicle $vehicle): JsonResponse
    {
        $vehicle->load(['category', 'openSession', 'owner']);

        return response()->json([
            'data' => new VehicleResource($vehicle),
            'owner' => $vehicle->owner ? ['id' => $vehicle->owner->id, 'name' => $vehicle->owner->name, 'phone' => $vehicle->owner->phone] : null,
            'recent_sessions' => ParkingSessionResource::collection(
                $vehicle->sessions()->with(['zone', 'slot', 'package', 'receipt'])->latest('entry_at')->limit(20)->get()
            ),
        ]);
    }

    public function update(AdminUpdateVehicleRequest $request, Vehicle $vehicle): VehicleResource
    {
        return new VehicleResource($this->vehicles->update($vehicle, $request->validated(), $request->user()));
    }
}
