<?php

namespace App\Http\Controllers\Api;

use App\Enums\FuelType;
use App\Http\Controllers\Controller;
use App\Http\Resources\PackageResource;
use App\Http\Resources\UserResource;
use App\Models\ParkingPackage;
use App\Models\PaymentMethod;
use App\Models\VehicleCategory;
use App\Models\ZoneType;
use App\Support\SessionFilters;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeController extends Controller
{
    public function show(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    /**
     * Lookup lists for forms (vehicle types, zone types, packages, payment methods).
     */
    public function referenceData(): JsonResponse
    {
        return response()->json(['data' => [
            'currency' => config('parking.currency'),
            'timezone' => config('parking.business_timezone'),
            'vehicle_categories' => VehicleCategory::where('is_active', true)->orderBy('sort_order')->get(['id', 'code', 'name', 'requires_plate', 'is_motorized'])
                ->map(fn ($c) => $c->toArray() + ['allowed_fuel_types' => FuelType::allowedFor($c->is_motorized)]),
            'fuel_types' => collect(FuelType::cases())->map(fn (FuelType $f) => ['value' => $f->value, 'label' => $f->label()]),
            'zone_types' => ZoneType::where('is_active', true)->get(['id', 'code', 'name', 'is_safety']),
            'packages' => PackageResource::collection(ParkingPackage::with('zoneTypes')->where('is_active', true)->orderBy('id')->get()),
            'session_filters' => SessionFilters::options(),
            'payment_methods' => PaymentMethod::where('is_active', true)->get(['code', 'name', 'confirmation', 'requires_reference']),
        ]]);
    }
}
