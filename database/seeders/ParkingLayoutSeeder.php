<?php

namespace Database\Seeders;

use App\Models\PackageRate;
use App\Models\ParkingPackage;
use App\Models\ParkingSlot;
use App\Models\ParkingZone;
use App\Models\PenaltyType;
use App\Models\VehicleCategory;
use App\Models\ZoneType;
use Illuminate\Database\Seeder;

/**
 * LOCAL DEVELOPMENT ONLY. Zone A–E layout, packages and EXAMPLE rates.
 * The BRD states prices and capacities are not approved; replace via the admin API.
 */
class ParkingLayoutSeeder extends Seeder
{
    /** Example slot counts per zone & category (NOT approved capacities). */
    private const LAYOUT = [
        'A' => ['short_time', 'Zone A – Short-term', ['bicycle' => 5, 'motorcycle' => 10, 'car' => 10, 'van' => 4, 'heavy' => 2]],
        'B' => ['daily', 'Zone B – Daily', ['motorcycle' => 8, 'car' => 10, 'van' => 4, 'heavy' => 2]],
        'C' => ['weekly', 'Zone C – Weekly', ['motorcycle' => 5, 'car' => 8, 'van' => 2]],
        'D' => ['monthly', 'Zone D – Monthly', ['motorcycle' => 5, 'car' => 8, 'van' => 2]],
        'E' => ['safety', 'Zone E – Safety / Premium', ['motorcycle' => 3, 'car' => 5, 'van' => 2]],
    ];

    /** EXAMPLE prices in LKR minor units (cents) — placeholders only. [price, overtime/hour, overtime daily cap] */
    private const RATES = [
        'short_time' => ['car' => [10000, 0, 150000], 'van' => [15000, 0, 200000], 'motorcycle' => [5000, 0, 60000], 'bicycle' => [2000, 0, 20000], 'heavy' => [30000, 0, 400000]],
        'daily' => ['car' => [150000, 10000, 150000], 'van' => [200000, 15000, 200000], 'motorcycle' => [60000, 5000, 60000], 'bicycle' => [20000, 2000, 20000], 'heavy' => [400000, 30000, 400000]],
        'night' => ['car' => [80000, 10000, 150000], 'van' => [100000, 15000, 200000], 'motorcycle' => [30000, 5000, 60000]],
        'weekly' => ['car' => [900000, 10000, 150000], 'van' => [1200000, 15000, 200000], 'motorcycle' => [350000, 5000, 60000]],
        'monthly' => ['car' => [3000000, 10000, 150000], 'van' => [4000000, 15000, 200000], 'motorcycle' => [1200000, 5000, 60000]],
        'safety' => ['car' => [50000, 5000, null], 'van' => [70000, 7000, null], 'motorcycle' => [30000, 3000, null]],
    ];

    /** EXAMPLE penalty catalogue (amounts NOT approved). */
    private const PENALTIES = [
        'lost_ticket' => ['Lost parking ticket', 50000],
        'rule_violation' => ['Parking rule violation', 100000],
        'unauthorized_parking' => ['Unauthorized parking', 150000],
    ];

    public function run(): void
    {
        foreach (self::PENALTIES as $code => [$name, $amount]) {
            PenaltyType::firstOrCreate(['code' => $code], ['name' => $name, 'amount_minor' => $amount, 'description' => 'Example penalty seeded for local development.']);
        }

        $types = ZoneType::pluck('id', 'code');
        $categories = VehicleCategory::pluck('id', 'code');

        foreach (self::LAYOUT as $code => [$type, $name, $slots]) {
            $zone = ParkingZone::firstOrCreate(['code' => $code], ['name' => $name, 'zone_type_id' => $types[$type]]);
            $zone->vehicleCategories()->syncWithoutDetaching(collect($slots)->keys()->map(fn ($c) => $categories[$c])->all());
            foreach ($slots as $category => $count) {
                for ($i = 1; $i <= $count; $i++) {
                    ParkingSlot::firstOrCreate(
                        ['parking_zone_id' => $zone->id, 'code' => sprintf('%s-%s-%03d', $code, strtoupper(substr($category, 0, 3)), $i)],
                        ['vehicle_category_id' => $categories[$category]]
                    );
                }
            }
        }

        $packages = [
            'short_time' => ['Short-time (hourly)', ParkingPackage::MODE_HOURLY, null, ['short_time', 'safety'], false],
            'daily' => ['Daily (24 hours)', ParkingPackage::MODE_FIXED, 1440, ['daily', 'safety'], false],
            'night' => ['Night', ParkingPackage::MODE_NIGHT, null, ['daily', 'safety'], false],
            'weekly' => ['Weekly (7 days)', ParkingPackage::MODE_FIXED, 10080, ['weekly', 'safety'], true],
            'monthly' => ['Monthly (30 days)', ParkingPackage::MODE_FIXED, 43200, ['monthly', 'safety'], true],
            'safety' => ['Safety parking add-on (per day)', ParkingPackage::MODE_ADDON, null, [], false],
        ];

        foreach ($packages as $code => [$name, $mode, $duration, $zoneTypes, $earlyExit]) {
            $package = ParkingPackage::firstOrCreate(['code' => $code], [
                'name' => $name, 'billing_mode' => $mode, 'duration_minutes' => $duration,
                'allows_early_exit_credit' => $earlyExit,
                // Weekly and monthly hold a dedicated slot for their whole validity (guaranteed parking).
                'guarantees_slot' => in_array($code, ['weekly', 'monthly'], true),
                'description' => 'Example package seeded for local development.',
            ]);
            $package->zoneTypes()->syncWithoutDetaching(collect($zoneTypes)->map(fn ($t) => $types[$t])->all());
            foreach (self::RATES[$code] as $category => [$price, $overtime, $cap]) {
                PackageRate::firstOrCreate(
                    ['parking_package_id' => $package->id, 'vehicle_category_id' => $categories[$category], 'version' => 1],
                    ['price_minor' => $price, 'overtime_hourly_minor' => $overtime, 'overtime_daily_cap_minor' => $cap, 'grace_minutes' => 15, 'effective_from' => now()->subYear()]
                );
            }
        }
    }
}
