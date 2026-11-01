<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\FuelType;
use App\Exceptions\ParkingException;
use App\Http\Controllers\Controller;
use App\Http\Requests\VehicleCategoryRequest;
use App\Models\Vehicle;
use App\Models\VehicleCategory;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Vehicle category catalogue. Categories are never deleted (history and pricing reference them);
 * deactivate instead. A deactivated category cannot be used for new vehicles or new entries,
 * but vehicles already parked can still exit and be billed normally.
 */
class VehicleCategoryController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function index(): JsonResponse
    {
        return response()->json(['data' => VehicleCategory::orderBy('sort_order')->orderBy('id')->get()
            ->map(fn (VehicleCategory $c) => $this->present($c))]);
    }

    public function store(VehicleCategoryRequest $request): JsonResponse
    {
        $category = VehicleCategory::create($request->validated() + ['is_motorized' => true])->refresh();
        $this->audit->log('vehicle_category.created', $category, [], $category->toArray());

        return response()->json(['data' => $this->present($category)], 201);
    }

    public function update(VehicleCategoryRequest $request, VehicleCategory $category): JsonResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($category, $data) {
            $locked = VehicleCategory::whereKey($category->id)->lockForUpdate()->firstOrFail();

            if (array_key_exists('is_motorized', $data) && (bool) $data['is_motorized'] !== $locked->is_motorized) {
                $allowed = FuelType::allowedFor((bool) $data['is_motorized']);
                $conflicts = Vehicle::where('vehicle_category_id', $locked->id)->whereNotIn('fuel_type', $allowed)->count();
                if ($conflicts > 0) {
                    throw new ParkingException("{$conflicts} vehicle(s) in this category have a fuel type that would become invalid. Update them first.", 'category_change_conflict', 409);
                }
            }
            if (($data['requires_plate'] ?? false) && ! $locked->requires_plate
                && Vehicle::where('vehicle_category_id', $locked->id)->where('is_reference', true)->exists()) {
                throw new ParkingException('Vehicles without a plate exist in this category; a plate cannot be made mandatory.', 'category_change_conflict', 409);
            }

            $original = $locked->getAttributes();
            $locked->update($data);
            $this->audit->logChanges('vehicle_category.updated', $locked, $original);
        });

        return response()->json(['data' => $this->present($category->refresh())]);
    }

    private function present(VehicleCategory $category): array
    {
        return $category->only(['id', 'code', 'name', 'requires_plate', 'is_motorized', 'is_active', 'sort_order']) + [
            'allowed_fuel_types' => FuelType::allowedFor($category->is_motorized),
        ];
    }
}
