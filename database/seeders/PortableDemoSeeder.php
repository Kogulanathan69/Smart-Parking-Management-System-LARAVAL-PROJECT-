<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleCategory;
use Illuminate\Database\Seeder;

/** Synthetic records for a newly created parkflow_demo_* database only. */
class PortableDemoSeeder extends Seeder
{
    public function run(): void
    {
        $database = (string) config('database.connections.'.config('database.default').'.database');
        if (! str_starts_with($database, 'parkflow_demo_')) {
            throw new \RuntimeException('Portable demo seeding is restricted to parkflow_demo_* databases.');
        }

        $this->call([ReferenceDataSeeder::class, ParkingLayoutSeeder::class, DemoUserSeeder::class]);

        $customer = User::where('email', 'customer@smart-parking.test')->firstOrFail();
        $staff = User::where('email', 'staff@smart-parking.test')->firstOrFail();
        $car = VehicleCategory::where('code', VehicleCategory::CAR)->firstOrFail();

        foreach ([
            ['WP DEMO-1001', 'Toyota', 'Aqua', 'Silver', 'hybrid'],
            ['WP DEMO-2002', 'Nissan', 'Leaf', 'White', 'electric'],
        ] as [$plate, $make, $model, $color, $fuel]) {
            Vehicle::firstOrCreate(['identifier' => Vehicle::normalizePlate($plate)], [
                'plate_number' => $plate,
                'vehicle_category_id' => $car->id,
                'owner_id' => $customer->id,
                'registered_by' => $staff->id,
                'make' => $make,
                'model' => $model,
                'color' => $color,
                'fuel_type' => $fuel,
            ]);
        }
    }
}
