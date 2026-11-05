<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\PackageRate;
use App\Models\ParkingPackage;
use App\Models\VehicleCategory;
use App\Services\ReportService;
use App\Support\Money;
use Illuminate\Http\JsonResponse;

/**
 * Customer-facing availability and published price list (read-only).
 * Exposes counts and published prices only: no slot codes, sessions, vehicles or other customers' data.
 */
class ParkingInfoController extends Controller
{
    public function __invoke(ReportService $reports): JsonResponse
    {
        $now = now();
        $categories = VehicleCategory::where('is_active', true)->orderBy('sort_order')->get(['id', 'code', 'name']);

        $zones = collect($reports->occupancy())->where('is_active', true)->map(fn ($z) => [
            'code' => $z['code'],
            'name' => $z['name'],
            'zone_type' => $z['zone_type'],
            'available' => $z['available'],
            'by_vehicle_category' => collect($z['by_vehicle_category'])->map(fn ($c) => [
                'vehicle_category' => $c['vehicle_category'],
                'available' => $c['available'],
                'total' => $c['total'],
            ])->values(),
        ])->values();

        $packages = ParkingPackage::where('is_active', true)
            ->where('billing_mode', '!=', ParkingPackage::MODE_ADDON)
            ->with(['rates' => fn ($q) => $q->where('effective_from', '<=', $now)
                ->where(fn ($w) => $w->whereNull('effective_to')->orWhere('effective_to', '>', $now))])
            ->orderBy('id')->get()
            ->map(fn (ParkingPackage $p) => [
                'code' => $p->code,
                'name' => $p->name,
                'billing_mode' => $p->billing_mode,
                'duration_minutes' => $p->duration_minutes,
                'guarantees_slot' => $p->guaranteesSlot(),
                'description' => $p->description,
                'prices' => $p->rates->sortBy('vehicle_category_id')->map(fn (PackageRate $r) => [
                    'vehicle_category' => $categories->firstWhere('id', $r->vehicle_category_id)?->code,
                    'price_minor' => $r->price_minor,
                    'price' => Money::format($r->price_minor),
                    'overtime_hourly_minor' => $r->overtime_hourly_minor,
                    'overtime_daily_cap_minor' => $r->overtime_daily_cap_minor,
                    'grace_minutes' => $r->grace_minutes,
                ])->filter(fn ($r) => $r['vehicle_category'] !== null)->values(),
            ]);

        return response()->json(['data' => [
            'currency' => config('parking.currency', 'LKR'),
            'timezone' => config('parking.business_timezone'),
            'as_of' => $now->toIso8601String(),
            'vehicle_categories' => $categories->map->only(['code', 'name'])->values(),
            'zones' => $zones,
            'packages' => $packages,
            'note' => 'Prices are the published rates. Your final charge is calculated at Check-Out from the rate recorded at your Check-In.',
        ]]);
    }
}
