<?php

namespace Database\Factories;

use App\Models\Vehicle;
use App\Models\VehicleCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vehicle>
 */
class VehicleFactory extends Factory
{
    public function definition(): array
    {
        $plate = strtoupper(fake()->bothify('WP ??? ####'));

        return [
            'identifier' => Vehicle::normalizePlate($plate),
            'plate_number' => $plate,
            'is_reference' => false,
            'vehicle_category_id' => fn () => VehicleCategory::where('code', VehicleCategory::CAR)->value('id') ?? VehicleCategory::factory(),
            'make' => fake()->randomElement(['Toyota', 'Honda', 'Suzuki', 'Nissan']),
            'model' => fake()->word(),
            'color' => fake()->safeColorName(),
        ];
    }
}
