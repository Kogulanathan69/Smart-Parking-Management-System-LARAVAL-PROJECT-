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
 *
 * Supports Vehicle Type -> Zone -> Package -> Price Preview.
 * Does not create bookings, reservations, payments or sessions.
 */
class ParkingInfoController extends Controller
{
    public function __invoke(ReportService $reports): JsonResponse
    {
        $now = now();

        // Active vehicle categories.
        $categories = VehicleCategory::where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'code', 'name']);

        // Live parking availability by zone and vehicle category.
        $zones = collect($reports->occupancy())
            ->where('is_active', true)
            ->map(fn ($z) => [
                'code' => $z['code'],
                'name' => $z['name'],
                'zone_type' => $z['zone_type'],
                'available' => $z['available'],

                'by_vehicle_category' => collect(
                    $z['by_vehicle_category']
                )->map(fn ($c) => [
                    'vehicle_category' => $c['vehicle_category'],
                    'available' => $c['available'],
                    'total' => $c['total'],
                ])->values(),
            ])
            ->values();

        // Published packages with active rates and allowed zone types.
        $packages = ParkingPackage::where('is_active', true)
            ->where('billing_mode', '!=', ParkingPackage::MODE_ADDON)
            ->with([
                'zoneTypes',

                'rates' => fn ($q) => $q
                    ->where('effective_from', '<=', $now)
                    ->where(fn ($w) => $w
                        ->whereNull('effective_to')
                        ->orWhere('effective_to', '>', $now)
                    ),
            ])
            ->orderBy('id')
            ->get()
            ->map(fn (ParkingPackage $p) => [
                'code' => $p->code,
                'name' => $p->name,
                'billing_mode' => $p->billing_mode,
                'duration_minutes' => $p->duration_minutes,
                'guarantees_slot' => $p->guaranteesSlot(),
                'description' => $p->description,

                // NEW: Zone compatibility for the price preview.
                // Empty array means allowed in all zone types,
                // matching ParkingPackage::allowedInZoneType().
                'allowed_zone_types' => $p->zoneTypes
                    ->pluck('code')
                    ->values()
                    ->all(),

                // Existing published price information.
                'prices' => $p->rates
                    ->sortBy('vehicle_category_id')
                    ->map(fn (PackageRate $r) => [
                        'vehicle_category' => $categories
                            ->firstWhere('id', $r->vehicle_category_id)?->code,

                        'price_minor' => $r->price_minor,
                        'price' => Money::format($r->price_minor),

                        'overtime_hourly_minor' => $r->overtime_hourly_minor,
                        'overtime_daily_cap_minor' => $r->overtime_daily_cap_minor,
                        'grace_minutes' => $r->grace_minutes,
                    ])
                    ->filter(fn ($r) => $r['vehicle_category'] !== null)
                    ->values(),
            ])
            ->values();

        return response()->json([
            'data' => [
                'currency' => config('parking.currency', 'LKR'),
                'timezone' => config('parking.business_timezone'),
                'as_of' => $now->toIso8601String(),

                'vehicle_categories' => $categories
                    ->map->only(['code', 'name'])
                    ->values(),

                'zones' => $zones,
                'packages' => $packages,

                'note' => 'Prices are the published rates. Your final charge is calculated at Check-Out from the rate recorded at your Check-In.',
            ],
        ]);
    }
}
