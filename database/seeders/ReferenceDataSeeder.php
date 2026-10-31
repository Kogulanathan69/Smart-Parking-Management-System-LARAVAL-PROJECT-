<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use App\Models\Shift;
use App\Models\VehicleCategory;
use App\Models\ZoneType;
use Illuminate\Database\Seeder;

/**
 * Idempotent reference data required in every environment (safe to re-run in production).
 */
class ReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['code' => VehicleCategory::BICYCLE, 'name' => 'Bicycle', 'requires_plate' => false, 'sort_order' => 1],
            ['code' => VehicleCategory::MOTORCYCLE, 'name' => 'Motorcycle', 'requires_plate' => true, 'sort_order' => 2],
            ['code' => VehicleCategory::CAR, 'name' => 'Car', 'requires_plate' => true, 'sort_order' => 3],
            ['code' => VehicleCategory::VAN, 'name' => 'Van', 'requires_plate' => true, 'sort_order' => 4],
            ['code' => VehicleCategory::HEAVY, 'name' => 'Lorry / Heavy vehicle', 'requires_plate' => true, 'sort_order' => 5],
        ] as $row) {
            VehicleCategory::updateOrCreate(['code' => $row['code']], $row);
        }

        foreach ([
            ['code' => 'short_time', 'name' => 'Short-time parking', 'is_safety' => false],
            ['code' => 'daily', 'name' => 'Daily parking', 'is_safety' => false],
            ['code' => 'weekly', 'name' => 'Weekly parking', 'is_safety' => false],
            ['code' => 'monthly', 'name' => 'Monthly parking', 'is_safety' => false],
            ['code' => 'safety', 'name' => 'Safety / premium parking', 'is_safety' => true],
        ] as $row) {
            ZoneType::updateOrCreate(['code' => $row['code']], $row);
        }

        foreach ([
            ['code' => 'cash', 'name' => 'Cash', 'confirmation' => PaymentMethod::CONFIRM_IMMEDIATE, 'requires_reference' => false],
            ['code' => 'card', 'name' => 'Card (manual terminal)', 'confirmation' => PaymentMethod::CONFIRM_IMMEDIATE, 'requires_reference' => true],
            ['code' => 'online', 'name' => 'Online payment', 'confirmation' => PaymentMethod::CONFIRM_GATEWAY, 'requires_reference' => false],
        ] as $row) {
            PaymentMethod::firstOrCreate(['code' => $row['code']], $row);
        }

        // BRD §10 suggested shifts (local time).
        foreach ([
            ['code' => 'morning', 'name' => 'Morning', 'start_time' => '06:00', 'end_time' => '14:00'],
            ['code' => 'evening', 'name' => 'Evening', 'start_time' => '14:00', 'end_time' => '22:00'],
            ['code' => 'night', 'name' => 'Night', 'start_time' => '22:00', 'end_time' => '06:00'],
        ] as $row) {
            Shift::firstOrCreate(['code' => $row['code']], $row + ['min_gate_staff' => 1]);
        }
    }
}
